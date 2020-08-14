<?php

namespace mahmad\Jira;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use mahmad\Jira\Fields;
use Carbon\Carbon;
class Ticket
{
    function __construct($issue,Fields $fields) 
	{
		foreach($fields as $field=>$code)
		{
			if($field == 'issuelinks')
			{
				$this->outwardIssue = $this->GetValue('outwardIssue',$issue,'outwardIssue');
				$this->inwardIssue = $this->GetValue('inwardIssue',$issue,'inwardIssue');
			}
			else
				$this->$field = $this->GetValue($code,$issue,$field);
		}
	}
	function DateToState($state)
	{
		$retval = null;
		for($i=0;$i<count($this->transitions);$i++)
		{
			$transition = $this->transitions[$i];
			if(strcasecmp($transition->toString,$state))
			{
				$retval = $transition->created;
			}
		}
		return $retval;
	}
	public function Process()
	{
	
		
	}
	private function GetValue($prop,$issue,$fieldname)
	{
		switch($prop)
		{
			case 'labels':
				if(isset($issue->fields->labels))
				{
					foreach($issue->fields->labels as &$label)
						$label = strtolower($label);
					return $issue->fields->labels;
					
				}
				return [];
			case 'key':
				return $issue->key;
				break;
			case 'summary':
				return $issue->fields->summary;
				break;
			case 'description':
				if(isset($issue->fields->description))
					return $issue->fields->description;
				return '';
				break;
			case 'timeremainingestimate':
				return $issue->fields->timeTracking->remainingEstimateSeconds;
				break;
			case'timeoriginalestimate':
				return $issue->fields->timeTracking->originalEstimateSeconds;
				break;
			case 'timetracking':
				return $issue->fields->timeTracking;
			case 'timespent':
				return $issue->fields->timeTracking->timeSpentSeconds;
				break;
			case 'updated':
				if(isset($issue->fields->updated))
				{
					$updated= new Carbon($issue->fields->updated);
					SetTimeZone($updated);
					return $updated->getTimestamp();
				}
				else 
				{
					return '';
				}
				break;
			case 'reporter':
				$reporter = [];
				$reporter['name'] = 'none';
				if(isset($issue->fields->reporter))
				{
					$reporter['name'] = $issue->fields->reporter->name;
					$reporter['displayName'] = $issue->fields->reporter->displayName;
					$reporter['emailAddress'] = $issue->fields->reporter->emailAddress;
				}
				return $reporter;
				break;
			case 'assignee':
				$assignee = [];
				$assignee['name'] = 'none';
				if(isset($issue->fields->assignee))
				{
					$assignee['name'] = $issue->fields->assignee->name;
					$assignee['displayName'] = $issue->fields->assignee->displayName;
					$assignee['emailAddress'] = $issue->fields->assignee->emailAddress;
				}
				return $assignee;
				break;
			case 'fixVersions':
				$cstr = [];
				if(isset($issue->fields->fixVersions))
				{
					foreach($issue->fields->fixVersions as $fixVersion)
					{
						$cstr[] = strtolower($fixVersion->name);
					}
				}
				
				return $cstr;
				break;
			case 'components':
				$cstr = [];
				if(isset($issue->fields->components))
				{
					
					foreach($issue->fields->components as $component)
					{
						$cstr[] = strtolower($component->name);
					}
				}
				return $cstr;
				break;
			case 'project':
				return $issue->fields->project->key;
				break;
			case 'created':
				if(isset($issue->fields->created))
				{
					$created= new Carbon($issue->fields->created);
					SetTimeZone($created);
					return $created->getTimestamp();
				}
				else 
				{
					return '';
				}
				break;
			break;
			case 'resolutiondate':
				if(isset($issue->fields->resolutiondate))
				{
					$resolutiondate= new Carbon($issue->fields->resolutiondate);
					SetTimeZone($resolutiondate);
					return $resolutiondate->getTimestamp();
				}
				else 
				{
					return '';
				}
				break;
			case 'subtasks':
				$subtasks = [];
				if(isset($issue->fields->subtasks))
				{
					foreach($issue->fields->subtasks as $subtask)
					{
						$subtasks[$subtask->key] = $subtask->key;
					}
				}
				return $subtasks;
				break;
			case 'duedate':
				if(isset($issue->fields->duedate))
				{
					$duedate= new Carbon($issue->fields->duedate);
					SetTimeZone($duedate);
					return $duedate->getTimestamp();
				}
				else 
				{
					return '';
				}
				break;
			case 'resolution':
			    if(isset($issue->fields->resolution->name))
				{
					return  $issue->fields->resolution->name;
				}
				else 
					return '';
				break;
			case 'status':
				return  strtoupper($issue->fields->status->name);
				break;
			case 'subtask':
				if(!isset($issue->fields->issuetype))
					dd("ERROR::Enable issuetype fields for subtask");
				return  $issue->fields->issuetype->subtask;
				break;
			case 'issuetypecategory':
				if(!isset($issue->fields->issuetype->name))
					dd("ERROR::Enable issuetype fields for issuetypecategory");
				if(function_exists('MapIssueTypeToCategory'))
					return MapIssueTypeToCategory(strtolower($issue->fields->issuetype->name)); 
				else
					dd('MapIssueTypeToCategory() is not declared to handle field='.$fieldname);
				break;
			case 'issuetype':
				return  strtolower($issue->fields->issuetype->name);
				break;
			case 'statuscategory':
				if(!isset($issue->fields->status))
					dd("ERROR::Enable status fields for statuscategory");
				if($issue->fields->status->statuscategory->id == 2)
					return 'OPEN';
				else if($issue->fields->status->statuscategory->id == 3)
					return 'RESOLVED';
				else if($issue->fields->status->statuscategory->id == 4)
					return 'INPROGRESS';
				else
					dd($issue->key." has unknown category");
			
				break;
			case 'priority':
				if(isset($issue->fields->priority))
				{
					if($issue->fields->priority->name == 'Blocker')
						return 1;
					else if($issue->fields->priority->name == 'Critical')
						return 2;
					else if($issue->fields->priority->name == 'Major')
						return 3;
					else if($issue->fields->priority->name == 'Medium')
						return 4;
					else
						return 5;
				}
				break;
			case 'transitions':
				$transitions = [];
				if(!isset($issue->changelog->histories))
					return $transitions;
				foreach($issue->changelog->histories as $history)
				{
					foreach($history->items as $item)
					{
						if($item->field == "status")
						{
							$obj =  new \StdClass();
							$created= new Carbon($history->created);
							SetTimeZone($created);
							$obj->date = $created->getTimestamp();
							$obj->from = $item->fromString;
							$obj->to = $item->toString;
							$transitions[] = $obj;
						}
					}
				}
				return $transitions;
				break;
			case 'inwardIssue':
				$issuelinks = [];
				if(!isset($issue->fields->issuelinks))
					return [];
				
				foreach($issue->fields->issuelinks as $issuelink)
				{
					if(isset($issuelink->inwardIssue))
					{
						$issuelinks[strtolower($issuelink->type->inward)][]=$issuelink->inwardIssue->key;
					}
				}
				return $issuelinks;
				break;
			case 'inwardIssue':
				$issuelinks = [];
				if(!isset($issue->fields->issuelinks))
					return [];
				
				foreach($issue->fields->issuelinks as $issuelink)
				{
					if(isset($issuelink->inwardIssue))
					{
						$issuelinks[strtolower($issuelink->type->inward)][]=$issuelink->inwardIssue->key;
					}
				}
				return $issuelinks;
				break;
			case 'outwardIssue':
				$issuelinks = [];
				if(!isset($issue->fields->issuelinks))
					return $issuelinks;
				foreach($issue->fields->issuelinks as $issuelink)
				{
					if(isset($issuelink->outwardIssue))
					{
						$issuelinks[strtolower($issuelink->type->outward)][]=$issuelink->outwardIssue->key;
					}
				}
				return $issuelinks;
				break;
			default:
				if(function_exists('IssueParser'))
				{
					return IssueParser($prop,$issue,$fieldname); 
					break;
				}
				else
				{
					dd('IssueParser() is not declared to handle field='.$fieldname);
				}
				break;
		}
	}
}
