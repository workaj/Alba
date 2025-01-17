<?php

defined('CMSPATH') or die; // prevent unauthorized access

class Content_Search {
	// TODO
	// make sure that filters cols are added to list_fields ? only relevant for code/admin backend views
	// 
	// $order_by="id", $type_filter=false, $id=null, $tag=null, $published_only=null, $list_fields=[], 
	// $ignore_fields=[], $filter_field=null, $filter_val=null, $page=0, $search="", $custom_pagination_size=null
	public $order_by;
	public $order_direction;
	public $type_filter;
	public $published_only;
	public $list_fields;
	public $ignore_fields;
	public $created_by_cur_user;
	public $page;
	public $searchtext;
	public $fetch_all; // boolean - if list_fields not set, get all not just 'list' items from json
	public $page_size;
	public $category; // category id to match
	public $tags; // array of tag ids to match 
	public $filters; // array of assoc arrays where 0=colname and 1=value to match e.g. [['note','test']] - note custom fields need f_ prefix
	private $count; // set after query is exec() shows total potential row count for paginated calls
	private $search_pdo_params;
	private $filter_pdo_params;
	private $custom_search_params;

	public function __construct() {
		$this->order_by = "id";
		$this->order_direction = "DESC";
		$this->type_filter = 1;
		$this->published_only = false;
		$this->page=1;
		$this->searchtext="";
		$this->ignore_fields=[];
		$this->list_fields=[];
		$this->count=0;
		$this->filters=[];
		$this->fetch_all = false;
		$this->category = null;
		$this->tags=[];
		$this->filter_pdo_params = [];
		$this->search_pdo_params = [];
		$this->custom_search_params = [];
		$this->created_by_cur_user = false; // restrict to created by currently logged in user. 
		$this->page_size=Configuration::get_configuration_value ('general_options', 'pagination_size'); // default to system default
	}	

	private function field_in_filters($field_name) {
		//CMS::pprint_r ("checking if {$field_name} is in: ");
		//CMS::pprint_r ($this->filters);
		$field_name = "f_" . $field_name;
		foreach ($this->filters as $filter) {
			if ($filter[0]==$field_name) {
				return $filter[1];
			}
		}
		return false;
	}

	public function get_count() {
		return $this->count;
	}

	public function exec() {
		// Create and run query based on criteria in object properties
		// Return DB fetchAll array
		// Set $this->count to number of rows returned WITHOUT LIMITS IN PACE
		
		if ($this->type_filter) {
			// get list fields from custom_fields.json file
			if (!is_numeric($this->type_filter)) {
				$this->type_filter= Content::get_content_type_id($this->type_filter);
				if (!$this->type_filter) {
					CMS::Instance()->show_error('Unable to determine content type');
				}
			}
			$location = Content::get_content_location($this->type_filter);
			$custom_fields = JSON::load_obj_from_file(CMSPATH . '/controllers/' . $location . '/custom_fields.json');
			if (!$this->list_fields || $this->fetch_all) {
				// no fields request, so see if we need to get all or just get list items from json
				if ($this->fetch_all) {
					// get all saveable, not explicitly ignored fields
					foreach ($custom_fields->fields as $custom_field) {
						if (!in_array($custom_field_name,$this->ignore_fields)) {
							if (isset($custom_field->save)) {
								if ($custom_field->save===true) {
									$this->list_fields[] = $custom_field->name;
								}
							}
							else {
								// assume saveable, add to query list
								$this->list_fields[] = $custom_field->name;
							} 
						}
					}
				}
				else {
					// don't want all, but....
					// mimimally get all fields in 'list' property in custom_fields
					// checking it's not ignored and is an actual saveable field
					if (property_exists($custom_fields,'list')) {
						foreach ($custom_fields->list as $list_name) {
							if (!in_array($custom_field_name,$this->ignore_fields)) {
								// check if field is explicitly saveable or no saveable option set
								foreach ($custom_fields->fields as $custom_field) {
									if ($custom_field->name==$list_name) {
										if (isset($custom_field->save)) {
											if ($custom_field->save===true) {
												$this->list_fields[] = $custom_field->name;
											}
										}
										else {
											// assume saveable, add to query list
											$this->list_fields[] = $custom_field->name;
										} 
									}
								}
							}
						}
					} 
				}
			}
		} 
		$query = "select";
		$select = " c.id, c.state, c.content_type, c.title, c.alias, c.ordering, c.start, c.end, c.created_by, c.updated_by, c.note, c.category, cat.title  as catname";
		if ($this->list_fields) {
			foreach ($this->list_fields as $field) {
				$select .= " ,f_{$field}_t.content as f_{$field}";
			}
		}
		$count_select = " count(*) as c ";

		$select = Hook::execute_hook_filters('custom_content_search_select', $select, $this->type_filter); 

		$from = " from ( content c ";

		// if custom field exists as filter - needs to be added in from/where not as left join
		// also save filter value to filter_pdo_params
		foreach ($this->list_fields as $field) {

			if (array_key_exists($field, $this->filters)) {
				$this->filter_pdo_params[] = $this->filters[$field];
				$from .= ", content_fields f_{$field}_t ";
			}
		}

		$from .= " ) left join categories cat on c.category=cat.id ";

		$from = Hook::execute_hook_filters('custom_content_search_from', $from, $this->type_filter); 

		// left join custom field fields
		// ONLY where not in filters
		if ($this->list_fields) {	
			foreach ($this->list_fields as $field) {
				if (!array_key_exists($field, $this->filters)) {
					$from .= " left join content_fields f_{$field}_t on f_{$field}_t.content_id=c.id and f_{$field}_t.name='{$field}' ";	
				}
			}
		}

		$where = ' where ';

		if ($this->published_only) {
			$where .= " c.state > 0 ";
		}
		else {
			$where .= " c.state >= 0 ";
		}
		if ($this->searchtext) {
			$where .= " AND (c.title like ? or c.note like ?) "; 
		}

		if ($this->tags) {
			// check array of ints
			// guaranteed to be arr of ints in core, but not in the wild...
			$tags_ok = true;
			foreach ($this->tags as $t) {
				if (!is_numeric($t)) {
					$tags_ok = false;
					break;
				}
			}
			if ($tags_ok) {
				// safe to implode without param injection
				$where .= " and c.id in (select content_id from tagged where tag_id in (" . implode(',', $this->tags) . ")) ";
			}
		}

		
		if ($this->type_filter && is_numeric($this->type_filter)) {
			$where .= " and c.content_type={$this->type_filter} ";
		}

		// custom fields being filtered
		if ($this->list_fields) {
			foreach ($this->list_fields as $field) {			
				if (array_key_exists('f_' . $field, $this->filters)) {
					//CMS::pprint_r ('Got filter for custom field ' . $field);
					$this->filter_pdo_params[] = $this->filters['f_'.$field];
					$where .= " and f_{$field}_t.content_id=c.id and f_{$field}_t.name='{$field}' ";	
					$where .= " and f_{$field}_t.content = ? ";
				}
			}
		}

		// required fields filter
		foreach ($this->filters as $key => $value) {
			if (strpos($key,'f_')===false) {
				// not custom field
				// check if core field (nb - content type handled elsewhere in class, as more common)
				if (in_array($key,['state','id','alias','title','category','created_by','created','updated_by','updated','note','start','end'])) {
					// add value to params for safety
					$this->filter_pdo_params[] = $value;
					$where .= " and c." . $key . " = ? " ;
				}
			}
		}

		if ($this->category && is_numeric($this->category)) {
			$where .= " AND c.category=" . $this->category . " "; // safe to inject - checked for number
		}

		if ($this->created_by_cur_user) {
			$where .= " AND created_by=" . CMS::Instance()->user->id . " "; // safe to inject - will be int 100%
		}

		$where = Hook::execute_hook_filters('custom_content_search_where', $where, $this->type_filter); 
		
		$this->custom_search_params = Hook::execute_hook_filters('custom_content_search_params', $this->custom_search_params, $this->type_filter); 

		$count_query = $query . $count_select . $from . $where;
		$query = $query . $select . $from . $where;

		
		

		if ($this->order_by) {
			$query .= " order by `" . $this->order_by . "` " . $this->order_direction;
		}
		if ($this->page) {
			if (is_numeric($this->page_size) && is_numeric($this->page)) {
				$query .= " LIMIT " . (($this->page-1)*$this->page_size) . "," . $this->page_size;
			}
		}
		/* CMS::pprint_r ($this->filters);*/
		//CMS::pprint_r ($this->filter_pdo_params);
		//CMS::pprint_r ($query); die(); 

		/* CMS::pprint_r ($this->custom_search_params);
		CMS::pprint_r ($this->filters);
		CMS::pprint_r ($this->list_fields);
		if ($this->filters) {
			CMS::pprint_r ($query); die(); 
		}
		CMS::pprint_r ($query); die(); */ 

		if ($this->searchtext) {
			$like = '%'.$this->searchtext.'%';
			$result = DB::fetchall($query,array_merge([$like,$like], $this->filter_pdo_params ?? [], $this->custom_search_params ?? [])); // title and note
			// set count
			$this->count = DB::fetch($count_query,array_merge([$like,$like], $this->filter_pdo_params ?? [], $this->custom_search_params ?? []))->c ?? 0;
		}
		else {
			$result = DB::fetchall($query, array_merge($this->filter_pdo_params ?? [], $this->custom_search_params ?? []) );
			// set count
			$this->count = DB::fetch($count_query, array_merge($this->filter_pdo_params ?? [], $this->custom_search_params ?? []) )->c ?? 0;
		}
		return $result;
	}
}