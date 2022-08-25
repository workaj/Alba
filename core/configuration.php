<?php
defined('CMSPATH') or die; // prevent unauthorized access

class Configuration {
	public $id;
	public $name; 
	public $configuration;

	static public function get_configuration_value ($form_name, $setting_name, $pdo=null) {
		// pdo can be passed so this function works inside of core cms.php file during construction
		// without having to reference an instance of itself
		// TODO - fix json query
		// this is unsafe - not preparing $setting_name is BAD
		// mitigating with check for spaces
		// perhaps attempt CONCAT in json path section with ? preparation
		if (!$pdo) {
			$pdo = CMS::Instance()->pdo;
		}

		if (strpos($setting_name, ' ') !== false) {
			// setting names should not have spaces I'm guessing?
			return false;
		}

		// fallback - get complete json and get property in PHP
		$configuration = DB::fetch("select configuration from configurations where name=?", [$form_name]);
		if ($configuration) {
			$config = json_decode($configuration->configuration);
			if (property_exists($config, $setting_name)) {
				return $config->{$setting_name};
			}
		}
		// not in db, get default from form
		$form_path = CMSPATH . "/admin/forms/" . $form_name . ".json";
		$form = JSON::load_obj_from_file ($form_path);
		//CMS::pprint_r ($form);
		$default = null; 
		if (property_exists($form,'fields')) { 
			foreach ($form->fields as $field) {
				if (property_exists($field,"name")) { 
					if ($field->name==$setting_name) {
						if (property_exists($field, "default")) { 
							if (Config::$debug) {
								CMS::log('Default value retrieved from form file for: ' . $setting_name . " from " . $form_name . " [{$field->default}]");
							}
							return $field->default;
						}
					}
				}
			}
		}
		CMS::log('No default value found for config field: ' . $setting_name);
		return false; // got here, got nuthin
	}

	function __construct($form) {
		$this->id = null;
		$this->name = $form->id;
		$this->form = $form;
		$this->configuration = new stdClass();
		$this->load_from_form($this->form);
	}

	public function load_from_form($form) {
		foreach ($form->fields as $field) {
			// default is either actual default from json or updated value from model or similar
			$this->configuration->{$field->name} = $field->default; 
		}
	}

	public function load_from_db() {
		// set config and form fields from configurations table entry
		$result = DB::fetch("select * from configurations where name=?", [$this->name]);
		if ($result) {
			// update object configuration
			$this->configuration = json_decode($result->configuration);
			// update config object form field values for display
			foreach ($this->form->fields as $field) {
				if ($this->configuration->{$field->name}===null||$this->configuration->{$field->name}===false) {
					// nothing stored in db for field (literally - 0 is fine :) 
					// do nothing - leave field default as default from form json
				}
				else {
					$field->default = $this->configuration->{$field->name};
				}
			}
			return $this;
		}
		else {
			return false;
		}
	}

	public function save() {
		// update or insert new set of configuration options
		$ok = DB::exec("INSERT INTO configurations (name,configuration) VALUES (?,?) ON DUPLICATE KEY UPDATE configuration=?", array($this->name, $json_config, $json_config));
		if ($ok) {
			return $this;
		}
		else {
			return false;
		}
	}
}