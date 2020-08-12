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
			$obj->issueId = $worklog->issueId;
			$obj->author = $worklog->author;
			$obj->comment = $worklog->comment;
			unset($obj->author['avatarUrls']);
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
		dump($key." ".$timeoriginalestimate." ".$timeremainingestimate." ".$timespent);
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