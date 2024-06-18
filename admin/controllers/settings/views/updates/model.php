<?php
defined('CMSPATH') or die; // prevent unauthorized access

// any variables created here will be available to the view

$segments = CMS::Instance()->uri_segments;

$submitted = Input::getvar('update_please');

$channel = Config::$channel ?? 'stable';

$latest = new stdClass();
$latest->version = null;
$update_domain = Config::$updatedomain ?? "alba.holtbosse.com";
$latest_json = file_get_contents("https://" . $update_domain . "/version.json");

if (Config::debugwarnings()) {
	CMS::pprint_r ($latest_json);
}
if ($latest_json) {
	$latest = json_decode($latest_json);
	$latest_version_current_channel = $latest->{$channel} ?? $latest->version;
}
else {
	$latest_version_current_channel = null;
}



// Legacy DB Checks / Fixes

// check page_options column in pages table
$page_options_ok = DB::fetchall("show columns FROM `pages` LIKE 'page_options'");
if (!$page_options_ok) {
	// add column
	DB::exec("ALTER TABLE `pages` ADD `page_options` text NOT NULL COMMENT 'seo and og settings';");
	$fixed_ok = true;
}

$plugins_table_ok = DB::fetchall("SELECT * FROM information_schema.tables WHERE table_name = 'plugins' LIMIT 1;");;
if (!$plugins_table_ok) {
	DB::exec("DROP TABLE IF EXISTS `plugins`;");
	DB::exec("CREATE TABLE `plugins` (
	  `id` int(11) NOT NULL,
	  `state` tinyint(4) NOT NULL DEFAULT '0',
	  `title` varchar(255) NOT NULL,
	  `location` varchar(255) NOT NULL,
	  `options` text COMMENT 'options_json',
	  `description` mediumtext
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
	DB::exec("ALTER TABLE `plugins` ADD PRIMARY KEY (`id`);");
	DB::exec("ALTER TABLE `plugins` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;");
}

//plugins_table_ok
$tags_table_ok = DB::fetchAll("SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'tags' AND COLUMN_NAME = 'parent'");
if (!$tags_table_ok) {
	DB::exec("ALTER TABLE tags ADD COLUMN `parent` int(11) DEFAULT NULL");
}

// create categories table if not exist

$categories_table_ok = DB::fetchAll("SELECT * FROM information_schema.tables WHERE table_name = 'categories'LIMIT 1;");
if (!$categories_table_ok) {
	DB::exec("CREATE TABLE `categories` (
	`id` int(11) NOT NULL,
	`state` int(11) NOT NULL DEFAULT '1',
	`title` varchar(64) NOT NULL,
	`content_type` int(11) NOT NULL COMMENT '-1 media, -2 user, -3 tag',
	`parent` int(11) NOT NULL DEFAULT '0'
  ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  DB::exec("ALTER TABLE `categories` ADD PRIMARY KEY (`id`);");
  DB::exec("ALTER TABLE `categories` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;");
}
// ensure cat columns exist in content and tags tables
$tag_category_ok = DB::fetchAll("SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'tags' AND COLUMN_NAME = 'category'");
if (!$tag_category_ok) {
	DB::exec("ALTER TABLE tags ADD COLUMN `category` int(11) DEFAULT 0");
}
/* $content_category_ok = DB::fetchAll("SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'content' AND COLUMN_NAME = 'category'");
if (!$content_category_ok) {
	DB::exec("ALTER TABLE content ADD COLUMN `category` int(11) DEFAULT 0");
} */

// ensure custom_fields col exist in category and tags tables
$custom_fields_category_ok = DB::fetchAll("SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'categories' AND COLUMN_NAME = 'custom_fields'");
if (!$custom_fields_category_ok) {
	DB::exec("ALTER TABLE categories ADD COLUMN `custom_fields` text");
}
$custom_fields_tag_ok = DB::fetchAll("SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'tags' AND COLUMN_NAME = 'custom_fields'");
if (!$custom_fields_tag_ok) {
	DB::exec("ALTER TABLE tags ADD COLUMN `custom_fields` text");
}

// ensure redirects table exists
$redirects_table_ok = DB::fetchAll("SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'redirects' LIMIT 1");
if (!$redirects_table_ok) {
	DB::exec("DROP TABLE IF EXISTS `redirects`;");
	DB::exec("
	CREATE TABLE `redirects` (
	  `id` int unsigned NOT NULL AUTO_INCREMENT,
	  `state` tinyint NOT NULL,
	  `old_url` varchar(2048) CHARACTER SET utf8mb4 NOT NULL,
	  `new_url` varchar(2048) CHARACTER SET utf8mb4 DEFAULT NULL,
	  `referer` varchar(2048) CHARACTER SET utf8mb4 DEFAULT NULL,
	  `note` varchar(255) CHARACTER SET utf8mb4 NOT NULL DEFAULT '',
	  `hits` int unsigned NOT NULL DEFAULT '0',
	  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	  `created_by` int unsigned NOT NULL DEFAULT '0',
	  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	  `updated_by` int unsigned NOT NULL DEFAULT '0',
	  `header` smallint NOT NULL DEFAULT '301',
	  PRIMARY KEY (`id`),
	  KEY `link_modifed` (`updated`),
	  KEY `old_url` (`old_url`(100))
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	");
}

// ensure user_actions table exists
$user_actions_table_ok = DB::fetchAll("SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'user_actions' LIMIT 1");
if (!$user_actions_table_ok) {
	DB::exec("DROP TABLE IF EXISTS `user_actions`;");
	DB::exec("
	CREATE TABLE `user_actions` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`userid` int(11) NOT NULL,
		`date` timestamp NOT NULL DEFAULT current_timestamp(),
		`type` varchar(255) NOT NULL,
		`json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
	");
}

// ensure user_actions_details table exists
$user_actions_details_table_ok = DB::fetchAll("SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'user_actions_details' LIMIT 1");
if (!$user_actions_details_table_ok) {
	DB::exec("DROP TABLE IF EXISTS `user_actions_details`;");
	DB::exec("
	CREATE TABLE `user_actions_details` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`action_id` int(11) NOT NULL,
		`json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`json`)),
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
	");
}

// Perform update if required

if ($submitted) { 
	// DO UPDATE
	$saved = true;
	// get appropriate update file
	$got_file = @file_put_contents(CMSPATH . "/latest.zip", fopen("https://".$update_domain."/" . $channel . ".zip", 'r'));
	if ($got_file) {
		$zip = new ZipArchive();
		$ok = $zip->open(CMSPATH . "/latest.zip", ZipArchive::CREATE);
		$saved = $zip->extractTo(CMSPATH);
		$zip->close();
		if ($saved && $ok) {
			CMS::Instance()->queue_message('System updated','success',Config::uripath()."/admin/settings/updates");
		}
		else {
			CMS::Instance()->queue_message('Error updating','danger',Config::uripath()."/admin/settings/updates");
		}
	}
	else {
		CMS::Instance()->queue_message('Error fetching latest update file (no fopen?) - Channel: ' . $channel, 'danger',Config::uripath()."/admin/settings/updates");
	}
}
