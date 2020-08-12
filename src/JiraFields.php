<?php
namespace mahmad\Jira;
class JiraFields
{
	private $customfields=[];
	private $fields=['key','summary','duedate','fixVersions','assignee','reporter','status','statuscategory','summary','resolutiondate'];
	function __construct(fields)
	{
		$this->fields = $fields;
	}
	function Custom()
	{
		return $this->customfields;
	}
	function Standard()
	{
		return $this->fields;
	}
	function __get($prop)
	{
		if(isset($this->customfields[$prop]))
			return $this->customfields[$prop];
	}
}
