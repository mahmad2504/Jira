<?php
namespace mahmad\Jira;

use JiraRestApi\Field\Field;
use JiraRestApi\Field\FieldService;
use JiraRestApi\JiraException;
use JiraRestApi\Configuration\ArrayConfiguration;

class CustomFields extends Command
{
	private $variable_customfields_map=[
				//'first_contact'=>'Date of First Response',
				//'reason_for_closure'=>'Reason For Closure.'
			];

    public function __construct()
    {
        parent::__construct();
    }
	function __get($prop)
	{
		return $this->variable_customfields_map[$prop];
	}
   
    public function Fetch()
    {
		
        //
		try 
		{
			$fieldService = new FieldService(
			 new ArrayConfiguration([
			 'jiraHost' => env('JIRA_EPS_URL'),
               // for basic authorization:
             'jiraUser' => env('JIRA_EPS_USERNAME'),
             'jiraPassword' => env('JIRA_EPS_PASSWORD'),
			]));
			
			// return custom field only. 
			$ret = $fieldService->getAllFields(Field::CUSTOM);
			foreach($ret as $field)
			{
				foreach($this->variable_customfields_map as $variablename=>$fieldname)
				{
					if(is_object($fieldname))
					{
						//echo $variablename."\n";
						continue;
					}
					if($fieldname == $field->name)
					{
						$this->variable_customfields_map[$variablename] = $field; 
						$this->variable_customfields_map[$variablename]->variablename = $variablename;
						
					}
				}
				//dd($field);
			}
			foreach($this->variable_customfields_map as $variablename=>$field)
			{
				if(!is_object($field))
				{
					echo "Field ".$field." not set\n";
					exit();
				}
			}
			file_put_contents("customefields.json",json_encode($this->variable_customfields_map));
			//dump($this->variable_customfields_map);
		} catch (JiraRestApi\JiraException $e) 
		{
			$this->assertTrue(false, 'testSearch Failed : '.$e->getMessage());
		}
		echo "Done";
    }
}
