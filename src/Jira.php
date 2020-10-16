<?php

namespace mahmad\Jira;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\Worklog;
use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Issue\TimeTracking;
use mahmad\Jira\Fields;
use mahmad\JiraTicket;
use JiraRestApi\Field\Field;
use JiraRestApi\Field\FieldService;
use Carbon\Carbon;
class Jira
{
	public static $issueService;
	public static $server;
	public function __construct()
	{
		
	}
	public static function Init($server='EPS')
	{
		self::$server = $server;
		self::$issueService = new IssueService(new ArrayConfiguration([
			 'jiraHost' => env('JIRA_'.$server.'_URL'),
              'jiraUser' => env('JIRA_'.$server.'_USERNAME'),
             'jiraPassword' => env('JIRA_'.$server.'_PASSWORD'),
		]));
	}
	public static function GetFieldService()
	{
		$fieldService = new FieldService(new ArrayConfiguration([
			 'jiraHost' => env('JIRA_'.self::$server.'_URL'),
              'jiraUser' => env('JIRA_'.self::$server.'_USERNAME'),
             'jiraPassword' => env('JIRA_'.self::$server.'_PASSWORD'),
		]));
		return $fieldService;
	}
	public static function ValidateQuery($query)
	{
		$data = null;
		$query = str_replace(" ","%20",$query);
		$resource=env('JIRA_'.self::$server.'_URL').'/rest/api/latest/search?jql='.$query.'&maxResults=0';
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
		CURLOPT_USERPWD => env('JIRA_'.self::$server.'_USERNAME').':'.env('JIRA_'.self::$server.'_PASSWORD'),
		CURLOPT_URL =>$resource,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => array('Content-type: application/json')));
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		if($data != null)
		{
			curl_setopt_array($curl, array(
				CURLOPT_POST => 1,
				CURLOPT_POSTFIELDS => $data
				));
		}
		$result = curl_exec($curl);
		$code = curl_getinfo ($curl, CURLINFO_HTTP_CODE);
		if($code == 200)
			return 1;
		return 0;
	}
	public static function GetStructureObjects($structid)
	{
		$data = '{"forests":[{"spec":{"type":"clipboard"},"version":{"signature":898732744,"version":0}},{"spec":{"structureId":'.$structid.',"title":true},"version":{"signature":0,"version":0}}],"items":{"version":{"signature":-157412296,"version":43401}}}';
		$resource=env('JIRA_'.self::$server.'_URL').'/rest/structure/2.0/poll';
		$curl = curl_init();
		curl_setopt_array($curl, array(
		CURLOPT_USERPWD => env('JIRA_'.self::$server.'_USERNAME').':'.env('JIRA_'.self::$server.'_PASSWORD'),
		CURLOPT_URL =>$resource,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => array('Content-type: application/json')));
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		if($data != null)
		{
			curl_setopt_array($curl, array(
				CURLOPT_POST => 1,
				CURLOPT_POSTFIELDS => $data
				));
		}
		$result = curl_exec($curl);
		
		$ch_error = curl_error($curl);
		$code = curl_getinfo ($curl, CURLINFO_HTTP_CODE);
		$objects = array();
		if($code == 200)
		{
			$data = json_decode($result,true);
			if(isset($data['forestUpdates']))
			{
				if(isset($data['forestUpdates'][1]['formula']))
				{
					$formula = $data['forestUpdates'][1]['formula'];
					$formula_array = explode(",",$formula);
					
					foreach($formula_array as $formula)
					{
						$detail = explode(":",$formula);
						$obj = new \StdClass();
						$obj->rwoid = $detail[0];
						$obj->level = $detail[1];
						$obj->taskid = $detail[2];
						if(strpos($detail[2], "/")>0)
						{}
						else
						{
							$objects[$obj->taskid] = $obj;
						}
					}
				}
			}
		}
		if(count($objects)==0)
			return null;
		return $objects;
	}
	public static function GetStructureQuery($objects)
	{
		$query = 'id in (' ;
		$del = "";
		foreach($objects as $object)
		{
			$query = $query.$del.$object->taskid;
			$del = ",";
		}
		$query = $query.")";
		return $query;
	}
	public static function StructureTree($objects,$tickets)
	{
		$output = [];
		$taskatlevel[0] = new \StdClass();
		//this->extid = $this->pextid.".".$this->pos;
		
		foreach($tickets as $key=>$ticket)
		{
			$objects[$ticket->id]->ticket=$ticket;
			$ticket->children = [];
		}
		foreach($objects as $object)
		{
			$level = $object->level;
			$parent = $taskatlevel[$level-1];
		
			//$object->ticket->extid = $parent.".".count($taskatlevel[$level]);
			$parent->children[] = $object->ticket;
			$taskatlevel[$level] = $object->ticket;
			if($level == 1)
				$output[] = $object->ticket;
		}
		return $output;
		//return $taskatlevel[0];
	}
	
	public static function WorkLogs($issueKey)
	{
		$worklogs = self::$issueService->getWorklog($issueKey)->getWorklogs();
		$wlgs = [];
		foreach($worklogs as $worklog)
		{
			$obj =  new \StdClass();
			$started = new Carbon($worklog->started);
			SetTimeZone($started);
			$obj->id = $worklog->id;
			$obj->started = $started->getTimeStamp();
			$obj->seconds = $worklog->timeSpentSeconds;
			//$obj->issueId = $worklog->issueId;
			$obj->author = $worklog->author['displayName'];
			//unset($obj->author['avatarUrls']);
			$wlgs[] = $obj;
		}
		return $wlgs;
	}
	public static function FetchChildren($tickets)
	{
		$query = '';
		$count = 0;
		$del = '';
		foreach($tickets as $ticket)
		{
			if(isset($ticket->linkedtasks))
				continue;
			$missing = 0;
			$children = [];
			if(isset($ticket->outwardIssue["implemented by"]))
			{
				foreach($ticket->outwardIssue["implemented by"] as $key)
				{
					if(!isset($tickets[$key]))
					{
						$query .= $del.$key;
						$del = ",";
						$missing = 1;
						$count++;
					}
					else
						$children[] = $tickets[$key];
				}
			}
			foreach($ticket->subtasks as $key)
			{
				if(!isset($tickets[$key]))
				{
					$query .= $del.$key;
					$del = ",";
					$missing = 1;
					$count++;
				}
				else
					$children[] = $tickets[$key];
			}
			if($missing == 0)
			{
				$ticket->children= $children;
				$ticket->linkedtasks = 1;
			}
		}
		if($count > 0)
		{
			$query = "key in (".$query.")";
			$ntickets = self::FetchTickets($query);
			$t = array_merge($tickets,$ntickets);
			return self::FetchChildren(array_merge($tickets,$ntickets));
		}
		else
			return $tickets;
    }
	public static function FetchEpics($tickets)
	{
		foreach($tickets as $ticket)
		{
			if(isset($ticket->issuesinepic))
				continue;
			if($ticket->issuetypecategory == 'EPIC')
			{
				$query =  "'Epic Link'=".$ticket->key;
				$children = self::FetchTickets($query);
				$ticket->children = [];
				$ticket->issuesinepic=1;
				foreach($children as $child)
				{
					echo $child->key."\n";
					if(isset($tickets[$child->key]))
						$ticket->children[] = $tickets[$child->key];
					else
					{
						$ticket->children[] = $child;
						$tickets[$child->key] = $child;
					}
				}
				return self::FetchEpics($tickets);
			}
		}
		return $tickets;
	}
	public static function SetQueries($tasks)
	{
		$link_implemented_by = 'implemented by';
		$link_parentof = 'is parent of';
		$link_testedby= 'is tested by';
		foreach($tasks as $task)
		{
			if($task->issuetypecategory == 'EPIC')
				$task->query = "'Epic Link'=".$task->key;
			else if(($task->issuetypecategory == 'REQUIREMENT')||($task->issuetypecategory == 'WORKPACKAGE'))
			{
				$del = '';
				$task->query = 'issue in linkedIssues("'.$task->key.'","'.$link_implemented_by.'")';
				$del = ' || ';
				$task->query .= $del.'issue in linkedIssues("'.$task->key.'","'.$link_parentof.'")';
				$del = ' || ';
				$task->query .= $del.'issue in linkedIssues("'.$task->key.'","'.$link_testedby.'")' ;
			}
		}
		return $tasks;
	}

	public static function BuildTree($jql)
	{
		$tickets = self::FetchTickets($jql);
		dump(count($tickets));
		$tickets = self::FetchEpics($tickets);
		dump(count($tickets));
		$tickets = self::FetchChildren($tickets);
		dump(count($tickets));
		$tickets = self::FetchEpics($tickets);
		dump(count($tickets));
		return $tickets;
	}
	public static function UpdateTimeTrack($key,$timeoriginalestimate,$timeremainingestimate,$timespent)
	{
		//dump($key." ".$timeoriginalestimate." ".$timeremainingestimate." ".$timespent);
		try 
		{
			//$timeTracking->setOriginalEstimate('1w 1d 6h');
			$current = self::$issueService->getTimeTracking($key);
			if(
			 ($current->getOriginalEstimateSeconds() != $timeoriginalestimate)||
			 ($current->getRemainingEstimateSeconds() != $timeremainingestimate))
			{
				$timeTracking = new TimeTracking;
				$timeTracking->setOriginalEstimate(($timeoriginalestimate/60)."m");
				$timeTracking->setRemainingEstimate(($timeremainingestimate/60)."m");
				$ret = self::$issueService->timeTracking($key, $timeTracking);
				dump($key." Updating timetrack");
			}
			$wlgs = self:: WorkLogs($key);
			$wlg = null;
			for($i=0;$i<count($wlgs);$i++)
			{
				$wlg = $wlgs[$i];
				if($wlg->comment == "@auto")
					break;
			}
			if($i==count($wlgs))
				$wlg = null;
			
			if($timespent > 0)
			{
				$workLog = new Worklog();
				$workLog->setComment('@auto')
				->setStarted("2016-05-28 12:35:54")
				->setTimeSpentSeconds($timespent);
				if($wlg == null) 
				{
					dump($key."  updating worklog");
					$ret = self::$issueService->addWorklog($key, $workLog);
				}
				else
				{
					$seconds = $wlg->seconds;
					//this  is test
					if($timespent != $seconds)
					{
						dump($key."  updating worklog");
						$ret = self::$issueService->editWorklog($key, $workLog, $wlg->id);
					}
				}
			}
		} catch (JiraRestApi\JiraException $e) 
		{
			dd($e->getMessage());
		}
	}
	public static function FetchTickets($jql,Fields $Jirafields)
	{
		
		$max = 500;
		$start = 0;
		
		if(isset($Jirafields->transitions))
			$expand = ['changelog'];
		else
			$expand = [];//['changelog'];
		$fields = [];
		
		foreach($Jirafields as $field=>$code)
		{
			$fields[ ]= $code;			
		}
		
		$issues = [];
		$start = 0;
		$max = 500;
		//dump($fields);
		while(1)
		{
			$data = self::$issueService->search($jql,$start, $max,$fields,$expand);
			if(count($data->issues) < $max)
			{
				foreach($data->issues as $issue)
				{
					$ticket = new Ticket($issue,$Jirafields);
					$issues[$ticket->key] = $ticket ;
				}
				//echo count($issues)." Found"."\n";
				return $issues;
			}
			foreach($data->issues as $issue)
			{
				$ticket = new Ticket($issue,$Jirafields);
				$issues[$ticket->key] = $ticket ;	
			}
			$start = $start + count($data->issues);
		}
	}
}