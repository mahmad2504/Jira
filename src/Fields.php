<?php
namespace mahmad\Jira;

use JiraRestApi\Field\Field;
use JiraRestApi\Field\FieldService;
use JiraRestApi\JiraException;
use JiraRestApi\Configuration\ArrayConfiguration;
use App;
class Fields 
{
	private $fields = [];
	private $default = ['key','status','issuelinks'];
	private $native = [];
	private $custom = ['story_points'=>'Story Points','sprint'=>'Sprint'];
	private $conf_filename = null;
	public function __construct($tag)
    {
		if (App::runningInConsole())
			$this->conf_filename = "data/".$tag.".json";
		else
			$this->conf_filename = "../data/".$tag.".json";
		
		if(file_exists($this->conf_filename))
			$this->fields = json_decode(file_get_contents($this->conf_filename));
		$this->init();
    }
	private function isAssoc(array $arr)
	{
		if (array() === $arr) return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}
	
	public function Set($fields)
	{
		if($this->isAssoc($fields))
			$this->custom = $fields;
		else
		{
			$this->native = $fields;
		}
	}
	private function init()
	{
		foreach($this->fields as $key=>$field)
		{
			if(is_object($this->fields->$key))
				$this->$key = $this->fields->$key->id;
			else
				$this->$key = $this->fields->$key;
		}
	}
	public function __get($field)
	{
		if(isset($this->fields->$field))
		{
			if(is_object($this->fields->$field))
			{
				return $this->fields->$field->id;
			}
			return $this->fields->$field;
		}
		else
			return null;
	}
    public function Dump()
    {
		$this->fields = [];
		try 
		{
			$fieldService = new FieldService();
			// return custom field only. 
			$ret = $fieldService->getAllFields(Field::CUSTOM);
			foreach($ret as $field)
			{
				foreach($this->custom as $variablename=>$fieldname)
				{
					if(is_object($fieldname))
					{
						//echo $variablename."\n";
						continue;
					}
					if($fieldname == $field->name)
					{
						$this->fields[$variablename] = $field; 
						$this->fields[$variablename]->variablename = $variablename;
					}
				}
            	//dd($field);
			}
			foreach($this->fields as $variablename=>$field)
			{
				if(!is_object($field))
				{
					echo "Field ".$field." not set\n";
					exit();
				}
			}
			foreach($this->native as $field)
				$this->fields[$field]=$field;
			foreach($this->default as $field)
				$this->fields[$field]=$field;
				
			file_put_contents($this->conf_filename,json_encode($this->fields));
			$this->fields = json_decode(file_get_contents($this->conf_filename));
			$this->init();
		} 
		catch (JiraRestApi\JiraException $e) 
		{
			$this->assertTrue(false, 'testSearch Failed : '.$e->getMessage());
		}
    }
}
