<?php

/**
 * A class to create an editing facility on top of a pureContent-enabled site
 * 
 * @package pureContentEditor
 * @license	http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author	{@link http://www.geog.cam.ac.uk/contacts/webmaster.html Martin Lucas-Smith}, University of Cambridge 2004-5
 * @author  {@link http://www.lucas-smith.co.uk/ Martin Lucas-Smith}
 * @version 1.00
 * 
 * REQUIREMENTS:
 * - This is a PHP5 application; it will not work with earlier versions of PHP
 * - PHP5 should ideally have the Tidy extension compiled/loaded
 * - Uses the FCKeditor DHTML component - www.fckeditor.net - to provide the richtext field; browser compatibility subject to FCKeditor requirements
 * - Requires libraries application.php, csv.php, directories.php, pureContent.php and ultimateForm.php, all available from http://download.geog.cam.ac.uk/
 * - Assumes that the server will supply a username - e.g. using AuthType or the University of Cambridge's Raven service
 * - Required mod_rewrite enabled in Apache
 */

/*

The following source code alterations must be made to FCKeditor 2.0 RC3

1. Customised configurations which cannot go in the PHP at present
Add the supplied file /_fckeditor/fckconfig-customised.js

2. https://sourceforge.net/forum/forum.php?thread_id=1167412&forum_id=257180 In files fckeditorcode_ie_2.js and fckeditorcode_gecko_2.js (in the /js/ folder)
Inline-comment out the inline-code:
html=html.replace(re,"<div$2</div>");

3. In connector.php add, after the opening include() lines
#MLS# Ensure $_GET['CurrentFolder'] in connector.php is not vulnerable to ../ attacks
$_GET['CurrentFolder'] = str_replace (array ('../', '..\\'), '', $_GET['CurrentFolder']);
#MLS# Fix down the user user files path
$Config['UserFilesPath'] = '/';

4. In io.php: add at the start of GetUrlFromPath() the line:
#MLS# Don't differentiate locations based on the resource type
$resourceType = '';

5. In io.php: add at the start of ServerMapFolder() the line:
#MLS# Don't differentiate locations based on the resource type
$resourceType = '';

6. In io.php: add at the start of GetRootPath() the line:
#MLS# Return the document root instead of (incorrectly) trying to work it out
return $_SERVER['DOCUMENT_ROOT'];

*/


# Create a class which adds editing functions to the pureContent framework
class pureContentEditor
{
	# Specify available arguments as defaults or as NULL (to represent a required argument)
	var $parameterDefaults = array (
		'editHostScheme' => 'http',				// Scheme of the editing site (i.e. http or https)
		'editHostName' => NULL,					// Hostname of the editing site
		'editHostPort' => 80,					// Port number of the editing site
		'liveSiteUrl' => NULL,					// Hostname of the live site
		'liveSiteRoot' => NULL,					// Directory where the main site files are located
		'filestoreRoot' => NULL,				// Directory where unapproved files are stored
		'serverAdministrator' => NULL,			// E-mail of the server administrator
		'userDatabase' => '/.users.csv',		// User database
		'permissionsDatabase' => '/.permissions.csv',	// Permissions database
		'changelog' => '/.changelog.csv',		// Changelog
		'richtextEditorWidth' => '100%',		// Richtext editor width in pixels e.g. 400 or percent e.g. '80%'
		'richtextEditorHeight' => '400px',		// Richtext editor height in pixels e.g. 400 or percent e.g. '80%'
		'richtextEditorEditorAreaCSS' => '/sitetech/global.css',	# CSS file to use in the editor area
		'richtextEditorBasePath' => '/_fckeditor/',	// Location of the DHTML editing component files
		'directoryIndex' => 'index.html',		// Default directory index name
		'newPageTemplate' => "\n<h1>Title goes here</h1>\n<p>Content starts here</p>",	// Default directory index file contents
		'messageSignatureGreeting' => 'Best wishes,',	// Preset text for the e-mail signature to users
		'pureContentTitleFile' => '.title.txt',	// pureContent title file name
		'pureContentMenuFile' => '.menu.html',	// pureContent menu file name
		'reviewPagesOpenNewWindow' => false,	// Whether pages for review should open in a new window or not
		'maximumFileAndFolderNameLength' => 25,	// Maximum number of characters for new files and folders
		'minimumUsernameLength' => 3,			// Minimum allowable length of a username
		'databaseTimestampingMode' => '.old',	// Whether to backup old CSV databases with .old ('.old') or a timestamp (true) or not at all (false)
		'enableAliasingChecks' => true,			// Whether to enable checks for a server-aliased page if a page is not found
		'developmentEnvironment' => false,		// Whether to run in development environment mode
		'hideDirectoryNames' => array ('.AppleDouble', ), // Directory names to exclude from directory listings
		'wordwrapViewedSubmittedHtml' => false,	// Whether to wordwrap submitted HTML in the confirmation display (will not affect the file itself)
		'bannedLocations' => array ('/sitetech/*', ),			// List of banned locations where pages/folders cannot be created and which will not be listed
		'allowPageCreationAtRootLevel' => false,	// Whether to allow page creation at root level (e.g. /page.html) rather than below (e.g. /directory/page.html)
		'backupReplacedLiveFiles' => true,		// Whether to backup files on the live site which have been replaced
		'externalLinksTarget'	=> '_blank',	// The window target name which will be instanted for external links (as made within the editing system) or false
		'imageAlignmentByClass'	=> true,		// Replace align="foo" with class="foo" for images
		'logoutlocation'	=> '/logout.html',	// False if there is no logout available from the authentication agent or the location of the page
	);
	
	# Specify the minimum version of PHP required
	var $minimumPhpVersion = '5.0.0';
	
	
	# Constructor
	function pureContentEditor ($parameters = array ())
	{
		# Enclose the entire application within a div to assist CSS styling
		echo "\n" . '<div id="purecontenteditor">';
		
		# Run the main program
		$this->main ($parameters);
		
		# Finish enclosing the entire application within a div to assist CSS styling
		echo "\n" . '</div>';
	}
	
	
	# Main function
	function main ($parameters)
	{
		# Load required libraries
		require_once ('application.php');
		require_once ('csv.php');
		require_once ('directories.php');
		require_once ('pureContent.php');
		require_once ('ultimateForm.php');
		
		# Ensure the setup is OK
		if (!$this->setup ($parameters)) {return false;}
		
		# Get the current page and attributes from the query string
		if (!list ($this->page, $this->action, $this->attributes) = $this->parseQueryString ()) {return false;}
		
		# Get the current directory for this page
		$this->currentDirectory = $this->currentDirectory ();
		
		# Assign the user
		$this->user = $_SERVER['REMOTE_USER'];
		
		# Get the users (which will force creation if there are none)
		if (!$this->users = $this->users ()) {return false;}
		
		# Get the users (which will force creation if there are none)
		if (!$this->administrators = $this->administrators ()) {return false;}
		
		# Ensure the user is in the user list
		if (!$this->userValid ()) {return false;}
		
		# Determine whether the user is an administrator
		$this->userIsAdministrator = $this->userIsAdministrator ();
		
		# Get the permissions for all users and then for the current user
		$this->permissions = $this->permissions ();
		$this->currentUserPermissions = $this->currentUserPermissions ();
		
		# Get the submissions
		$this->submissions = $this->submissions ();
		
		# Clean up the directory structure if necessary
		$this->cleanUp = $this->cleanUp ();
		
		# Determine any staging page and any real page, and whether to use the staging page
		$this->livePage = $this->livePage ();
		$this->particularPage = $this->particularPage ();
		$this->stagingPage = $this->stagingPage ();
		
		# Ensure that a page does exist
		if (!$this->ensurePagePresent ()) {return false;}
		
		# Determine whether to use the staging page
		$this->pageToUse = $this->pageToUse ();
		
		# Determine the editable version to use
		if (!$this->editableFile = $this->editableFile ()) {return false;}
		
		# Get the contents of the latest version
		$this->editableFileContents = $this->editableFileContents ();
		
		# Determine the type of the file
		$this->typeOfFile = $this->typeOfFile ();
		
		# Determine whether the page contains PHP
		$this->pageContainsPhp = $this->pageContainsPhp ();
		
		# Determine whether there is an administrator ban here
		$this->changesBannedHere = $this->changesBannedHere ();
		
		# Determine whether the user has rights here
		list ($this->userHasPageEditingRightsHere, $this->userHasPageCreationRightsHere, $this->userHasFolderCreationRightsHere) = $this->rights ();
		
		# Determine whether the user can edit the current page
		$this->userCanEditCurrentPage = $this->userCanEditCurrentPage ();
		
		# Get the available tasks
		$this->tasks = $this->tasks ();
		
		# Show the menu
		echo $this->showMenu ();
		
		# Check that the action is allowed
		if (!array_key_exists ($this->action, $this->tasks)) {
			echo "\n" . '<p class="failure">You appear to have requested a non-existent/unavailable function which is not available. Please use one of the links in the menu to continue.</p>';
			return false;
		}
		
		# If the function is administrative but the user is not an administrator, end
		if ($this->tasks[$this->action]['administratorsOnly'] && !$this->userIsAdministrator) {
			echo "\n" . '<p class="failure">You are not an administrator, so cannot perform the requested operation.</p>';
			return false;
		}
		
		# Take action
		$this->{$this->action} ($this->attributes);
	}
	
	
	# Function to setup the application
	function setup ($parameters)
	{
		# Check that all required arguments have been supplied, import supplied arguments and assign defaults
		foreach ($this->parameterDefaults as $parameter => $defaultValue) {
			if ((is_null ($defaultValue)) && (!isSet ($parameters[$parameter]))) {
				$setupErrors[] = "No '$parameter' has been supplied in the settings. This must be fixed by the administrator before this facility will work.";
			}
			$this->{$parameter} = (isSet ($parameters[$parameter]) ? $parameters[$parameter] : $defaultValue);
		}
		
		# Ensure the version of PHP is supported
		if (version_compare (PHP_VERSION, $this->minimumPhpVersion, '<')) {
			$setupErrors[] = "This program can only be run on PHP version {$this->minimumPhpVersion} or later.";
		}
		
		# Ensure that file uploads are enabled
		if (!ini_get ('file_uploads')) {$setupErrors[] = 'File uploading is not enabled - please ensure this is enabled in the server configuration.';}
		
		# Set PHP parameters
		ini_set ('error_reporting', 2047);
		ini_set ('display_errors', $this->developmentEnvironment);
		ini_set ('log_errors', !$this->developmentEnvironment);
		
		# Construct the edit site and live site URL
		$this->editSiteUrl = "{$this->editHostScheme}://{$this->editHostName}" . ($this->editHostPort != 80 ? ":{$this->editHostPort}" : '');
		
		# Ensure the URL is correct, i.e. the editing facility is not being run through an unauthorised location
		if (($_SERVER['_SERVER_PROTOCOL_TYPE'] != $this->editHostScheme) || ($_SERVER['SERVER_NAME'] != $this->editHostName) || ($_SERVER['SERVER_PORT'] != $this->editHostPort)) {$setupErrors[] = 'The editing facility must be run from the URL specified in the settings.';}
		
		# Check that the server is defining a remote user
		if (!isSet ($_SERVER['REMOTE_USER'])) {$setupErrors[] = 'The server did not supply a username, so the editing facility is unavailable.';}
		
		# Ensure the filestoreRoot and liveSiteRoot are not slash-terminated
		$this->filestoreRoot = ((substr ($this->filestoreRoot, -1) == '/') ? substr ($this->filestoreRoot, 0, -1) : $this->filestoreRoot);
		$this->liveSiteRoot = ((substr ($this->liveSiteRoot, -1) == '/') ? substr ($this->liveSiteRoot, 0, -1) : $this->liveSiteRoot);
		
		# Ensure the current page is not the instantiating stub file
		$this->stubFileLocation = ereg_replace ('^' . $this->liveSiteRoot, '', $_SERVER['SCRIPT_FILENAME']);
		
		# Confirm a list of banned locations (which also affects the administrator), including the stub file
		$this->bannedLocations = application::ensureArray ($this->bannedLocations);
		$this->bannedLocations[] = $this->stubFileLocation;
		
		# Ensure the filestore exists and is writable before continuing, if the location has been supplied
		if ($this->filestoreRoot) {
			if (!is_dir ($this->filestoreRoot)) {
				if (!@mkdir ($this->filestoreRoot, 0775, $recursive = true)) {
					$this->reportErrors ('There was a problem creating the main filestore directory.', "The filestoreRoot, which cannot be created, specified in the settings, is: {$this->filestoreRoot}/");
					return false;
				}
			}
			if (!$this->directoryIsWritable ($this->filestoreRoot)) {
				$setupErrors[] = 'It is not currently possible to write files to the filestore. The administrator needs to ensure the directory exists and fix the permissions first.';
			}
		}
		
		# Define an array for an additional message (used in several places);
		$this->additionalMessageWidget = array (
			'elementName'			=> 'message',
			'title'					=> 'Any additional message',
			'required'				=> false,
			'columns'				=> 40,
			'rows'					=> 3,
		);
		
		# Define the user and permissions database locations
		$this->userDatabase = $this->filestoreRoot . $this->userDatabase;
		$this->permissionsDatabase = $this->filestoreRoot . $this->permissionsDatabase;
		
		# Ensure PHP_EOL is defined (taken from http://cvs.php.net/co.php/pear/PHP_Compat/Compat/Constant/PHP_EOL.php?r=1.2 )
		if (!defined ('PHP_EOL')) {
			switch (strtoupper (substr (PHP_OS, 0, 3))) {
				case 'WIN':	// Windows
					define ('PHP_EOL', "\r\n");
		            break;
		        case 'DAR':	// Mac (Darwin)
		            define ('PHP_EOL', "\r");
		            break;
		        default:	// Unix
		            define ('PHP_EOL', "\n");
		    }
		}
		
		# Ensure the permissions database exists
		if (!file_exists ($this->permissionsDatabase)) {
			$permissionsDatabaseHeaders = array ('Key' , 'Username' , 'Location', 'Startdate', 'Enddate', /*'Self-approval', */);
			if (!csv::createNew ($this->permissionsDatabase, $permissionsDatabaseHeaders)) {
				$setupErrors[] = 'There was a problem creating the permissions database.';
			}
		}
		
		# Ensure the user database exists
		if (!file_exists ($this->userDatabase)) {
			$userDatabaseHeaders = array ('Username' , 'Forename' , 'Surname' , 'E-mail' , 'Administrator');
			if (!csv::createNew ($this->userDatabase, $userDatabaseHeaders)) {
				$setupErrors[] = 'There was a problem creating the user database.';
			}
		}
		
		# Ensure that live URL file opening is permissable
		if (!ini_get ('allow_url_fopen') && $this->enableAliasingChecks) {
			$setupErrors[] = 'Aliasing checks have been requested but external file opening is not enabled in the server master configuration.';
		}
		
		# Check that the query string is being correctly provided by Apache
		if (!explode ('&', $_SERVER['QUERY_STRING'])) {
			$setupErrors[] = 'The webserver is not providing the editing facility with the appropriate information correctly - the Rewrite configuration should be checked.';
		}
		
		# Check for any setup errors and end if any found
		if (isSet ($setupErrors)) {
			$this->reportErrors ($setupErrors);
			return false;
		}
		
		# Otherwise return success
		return true;
	}
	
	
	# Function to get the live page filename
	function livePage ()
	{
		# Construct the filename
		$file = $this->liveSiteRoot . $this->page;
		
		# Return the page filename or false
		return $livePageFile = (file_exists ($file) ? $file : false);
	}
	
	
	# Function to get a specified page filename
	function particularPage ()
	{
		# A particular page can be specified if the action is editing/browsing/reviewing and the page is specified as an attribute and exists
		if ($this->action != 'edit' && $this->action != 'browse' && $this->action != 'review') {return false;}
		if (!isSet ($this->submissions[$this->attributes])) {return false;}
		
		# Construct the filename
		return $particularPageFile = $this->attributes;
	}
	
	
	# Function to get the staging page filename
	function stagingPage ()
	{
		# Assume there is no staging page
		$stagingPage = false;
		
		# Loop through the submissions assigning the last (i.e. latest) file as the chosen one if there are several
		foreach ($this->submissions as $file => $attributes) {
			if ($this->page == ($attributes['directory'] . $attributes['filename'])) {
				$stagingPage = $file;	// NB This exists, otherwise it would not be in the submissions list
			}
		}
		
		# Return the filename or false
		return $stagingPage;
	}
	
	
	# Function to ensure that a page is present
	function ensurePagePresent ()
	{
		# Check here whether the directory contains an index file
		$this->directoryContainsIndex = $this->directoryContainsIndex ();
		
		# Start from the assumption that the page is present
		$pagePresent = true;
		
		# If the server has thrown a 404 and there is no staging page replicate this
		$this->pageIs404 = false;
		if (isSet ($_SERVER['REDIRECT_STATUS'])) {
			if ($_SERVER['REDIRECT_STATUS'] == '404') {
				if (!$this->stagingPage) {
					$this->pageIs404 = true;
					$pagePresent = false;
				}
			}
		}
		
		# If neither a live page nor a staging page is present, set the page as not found
		if (!$this->livePage && !$this->stagingPage) {
			$pagePresent = false;
		}
		
		# Assume that the page is not being aliased (may be overriden below)
		$this->pageIsBeingAliased = false;
		
		# If the page is not present, throw a 404
		if (!$pagePresent) {
			
			# If alias checking is allowed, check if the file exists on the live site; if it does then aliasing is likely to be in use
			if ($this->enableAliasingChecks) {
				if (@file_get_contents ($this->liveSiteUrl . $this->page)) {
					if (!$this->pageIs404) {
						$this->pageIsBeingAliased = true;
						return true;
					}
				}
			}
			
			# If there is no directory index, force creation
			if (!$this->directoryContainsIndex) {
				$this->action = 'newPage';
				return true;
			}
			
			# Otherwise throw a 404
			echo "\n</div>";
			application::sendHeader (404);
			echo "\n" . '<h1>Page not found</h1>';
			echo "\n" . '<p class="failure">The page you requested cannot be found.</p>';
			echo "\n<div>";
			return false;
		}
		
		# Otherwise return true
		return true;
	}
	
	
	# Function to check whether a current directory contains an index page
	function directoryContainsIndex ()
	{
		# Return false if it doesn't exist on the live site
		if (file_exists ($this->liveSiteRoot . $this->currentDirectory . $this->directoryIndex)) {return true;}
		
		# Return true if it exists in the filestore
		foreach ($this->submissions as $file) {
			if (($file['directory'] . $file['filename']) == ($this->currentDirectory . $this->directoryIndex)) {return true;}
		}
		
		# Return false otherwise
		return false;
	}
	
	
	# Function to determine which page to use
	function pageToUse ()
	{
		# If a particular page has been defined, return that
		if ($this->particularPage) {return 'particular';}
		
		# Flag yes if there is no live page (NB ensurePagePresent () will have been run by now)
		if (!$this->livePage) {return 'staging';}
		
		# Flag no if there is no staging page
		if (!$this->stagingPage) {return 'live';}
		
		# If, now that a staging page is confirmed present, the original is being requested, return false
		if ((($this->action == 'edit') || ($this->action == 'browse')) && ($this->attributes != 'original')) {return 'staging';}
		
		# Otherwise return false
		return 'live';
	}
	
	
	# Function to determine the latest version in use
	function editableFile ()
	{
		# Define the latest version
		switch ($this->pageToUse) {
			case 'live':
				$editableFile = $this->liveSiteRoot . $this->page;
				break;
			case 'particular':
				$editableFile = $this->filestoreRoot . $this->particularPage;
				break;
			case 'staging':
				$editableFile = $this->filestoreRoot . $this->stagingPage;
				break;
		}
		
		# Check that the file is readable (this is done rather than checking the return value of file_get_contents in editableFileContents () because the latter may return a zero-length file)
		if (!is_readable ($editableFile)) {
			$this->reportErrors ('There was a problem opening the file.', "The file name is {$editableFile} .");
			return false;
		}
		
		# Return true otherwise
		return $editableFile;
	}
	
	
	# Function to get the contents of the latest version in use
	function editableFileContents ()
	{
		# Get the contents (NB is readable has been done within editableFile (); no false checking is done here, as this could indicate a zero-length file)
		$contents = file_get_contents ($this->editableFile);
		
		# Pre-process the contents
		$contents = $this->preprocessContents ($contents);
		
		# Return the contents
		return $contents;
	}
	
	
	# Function to pre-process the page contents
	function preprocessContents ($content)
	{
		# List replacements
		$replacements = array (
			" src=\"{$this->editSiteUrl}/"	=> ' src="/',			// Ensure images are not prefixed with the current site's URL
			' src="([^/])'				=> ' src="' . $this->currentDirectory . '\\1',		// Ensure all images are absolute
		);
		
		# Replacement of image class with a similarly-named align attribute (this is then reversed afterwards - this is so that the DHTML editor picks up the alignment correctly
		if ($this->imageAlignmentByClass) {
			$replacements += array (
				'<img([^>]*) class="(left|center|centre|right)"([^>]*)>' => '<img\1 align="\2"\3>',
			);
		}
		
		# Perform the replacements
		foreach ($replacements as $find => $replace) {
			$content = eregi_replace ($find, $replace, $content);
		}
		
		# Return the contents
		return $content;
	}
	
	
	# Function to parse the query string and return the page and attributes
	function parseQueryString ()
	{
		# Get the query
		$query = explode ('&', $_SERVER['QUERY_STRING']);
		
		# Assign the page
		$page = (!isSet ($_SERVER['REDIRECT_SCRIPT_URL']) ? $query[0] : $_SERVER['REDIRECT_SCRIPT_URL']);
		
		# If the user has requested a directory, ensure it internally ends with the directory index
		if (substr ($page, -1) == '/') {$page .= $this->directoryIndex;}
		
		# Disallow loading of the instantiating stub file
		if ($page == $this->stubFileLocation) {return false;}
		
		# Obtain the current action and set the initial attributes
		$action = 'browse';
		$attributes = false;
		if (isSet ($query[1])) {
			$split = explode ('=', $query[1]);
			$action = $split[0];
			$attributes = (isSet ($split[1]) ? $split[1] : '');
		}
		
		# Return the query and attributes
		return array ($page, $action, $attributes);
	}
	
	
	# Function to get the current directory for this page
	function currentDirectory ()
	{
		# Get the current page
		$currentPage = str_replace ('\\', '/', $this->page);
		
		# Get the current directory
		$currentDirectory = (substr ($currentPage, -1) == '/' ? $currentPage : str_replace ('\\', '/', dirname ($currentPage)));
		
		# Slash-terminate if necessary
		if (substr ($currentDirectory, -1) != '/') {$currentDirectory .= '/';}
		
		# Return the result
		return $currentDirectory;
	}
	
	
	# Function to get users
	function users ()
	{
		# Get the data and return it
		if (!$users = csv::getData ($this->userDatabase)) {
			$this->userAdd ($firstRun = true);
			return false;
		}
		
		# Return the users
		return $users;
	}
	
	
	# Function to get administrators
	function administrators () {
		
		# Determine the administrators
		foreach ($this->users as $user => $attributes) {
			if ($attributes['Administrator']) {
				$administrators[] = $user;
			}
		}
		
		# Throw an error if there are no administrators
		if (!isSet ($administrators)) {
			$this->reportErrors ('There are no administrators assigned. Somehow the user database has been edited incorrectly. The administrator should reset the user database or edit it directly to ensure there is an administrator.');
			return false;
		}
		
		# Return the administrators
		return $administrators;
	}
	
	
	# Function to get users
	function permissions ()
	{
		# Get the data and return it
		$permissions = csv::getData ($this->permissionsDatabase);
		
		# Sort the permissions, maintaining key to data correlations; this will ensure that overriding rights are listed (and will thus be matched) first (i.e. tree>directory>page)
		ksort ($permissions);
		
		# Return the permissions
		return $permissions;
	}
	
	
	# Function to determine whether the user is an administrator
	function userIsAdministrator ()
	{
		# Return the result from the array
		return $this->users[$this->user]['Administrator'];
	}
	
	
	# Function to determine whether the page is banned
	function changesBannedHere ()
	{
		# Return the result directly
		return $this->matchLocation ($this->bannedLocations, $this->page);
	}
	
	
	# Function to determine whether the user has page creation rights here
	function rights ()
	{
		# Determine the user's rights
		$rights = $this->determineRights ();
		
		# Determine whether page creation is being disallowed at this location due to root level disallowing
		$rootPageCreationRestrictionApplies = ($this->currentDirectory == '/' && !$this->allowPageCreationAtRootLevel);
		
		# Determine the user's editing, page creation and folder creation rights
		$editing = ($rights);
		$pageCreation = (($rights == 'tree' || $rights == 'directory') && !$rootPageCreationRestrictionApplies);
		$folderCreation = ($rights == 'tree');
		
		# Return the values
		return array ($editing, $pageCreation, $folderCreation);
	}
	
	
	# Function to determine the user's rights overall
	function determineRights ()
	{
		# Return false if the page is banned
		if ($this->changesBannedHere) {return false;}
		
		# Return true if the user is an administrator
		if ($this->userIsAdministrator) {return true;}
		
		# Determine the locations in the current user's permissions for matching
		$locations = array ();
		foreach ($this->currentUserPermissions as $permission) {
			$locations[] = $this->permissions[$permission]['Location'];
		}
		
		# Otherwise return the user's rights in detail
		return $this->matchLocation ($locations, $this->page);
	}
	
	
	# Function to perform a location match
	function matchLocation ($locations, $test)
	{
		# Loop through each location (which are ordered such that overriding rights are listed (and will thus be matched) first (i.e. tree>directory>page)
		foreach ($locations as $location) {
			
			# Check for an exact match
			if ($location == $test) {
				return 'page';
			}
			
			# Check for pages in the same directory
			if (substr ($location, -1) == '/') {
				$page = ereg_replace ('^' . $location, '', $test);
				if (strpos ($page, '/') === false) {
					return 'directory';
				}
			}
			
			# Check for pages below the test location
			if (substr ($location, -1) == '*') {
				if (ereg ('^' . $location, $test)) {
					if ($location != ereg_replace ('^' . $location, '', $test)) {
						return 'tree';
					}
				}
			}
		}
		
		# Else return false
		return false;
	}
	
	
	# Function to determine whether the user can edit the current page
	function userCanEditCurrentPage ()
	{
		# Set a flag for whether the page contains the string <?php ; return false if found
		if ($this->pageContainsPhp) {return false;}
		
		# If the page is being aliased, the source is therefore not available so cannot be edited
		if ($this->pageIsBeingAliased) {return false;}
		
		# Otherwise return whether the user has rights here
		return $this->userHasPageEditingRightsHere;
	}
	
	
	# Function to determine whether the page contains PHP instructions
	function pageContainsPhp ()
	{
		# Set a flag for whether the page contains the string <?php ; return false if found
		return (strpos ($this->editableFileContents, '<?php') !== false);
	}
	
	
	# Function to get the type of file
	function typeOfFile ()
	{
		# Get the filename
		$filename = basename ($this->editableFile);
		
		# Title file, starts with the string contained in $this->pureContentTitleFile
		if (ereg ('^' . $this->pureContentTitleFile, $filename)) {return 'titleFile';}
		
		# Menu file, starts with the string contained in $this->pureContentMenuFile
		if (ereg ('^' . $this->pureContentMenuFile, $filename)) {return 'menuFile';}
		
		# Default to a page
		return 'page';
	}
	
	
	# Function to get menu tasks and their permissions
	function tasks ()
	{
		# Create an array of the tasks
		$tasks = array (
			'browse' => array (
				'title' => 'Browse site',
				'tooltip' => 'Browse the site as normal and find pages to edit',
				'url' => $this->page /*. ($this->attributes == 'original' ? '=original' : '')*/,
				'administratorsOnly' => false,
				'grouping' => 'Main actions',
			),
			
			'edit' => array (
				'title' => 'Edit this page',
				'tooltip' => 'Edit the current page',
				'url' => '?edit' /*. ($this->attributes == 'original' ? '=original' : '')*/,
				'administratorsOnly' => false,
				'grouping' => 'Main actions',
				'check' => 'userCanEditCurrentPage',
			),
			
			'section' => array (
				'title' => 'Create new section',
				'tooltip' => 'Create a new section (set of pages)',
				'administratorsOnly' => false,
				'grouping' => 'Main actions',
				'check' => 'userHasFolderCreationRightsHere',
			),
			
			'newPage' => array (
				'title' => 'Create new page',
				'tooltip' => 'Create a new page within an existing section',
				'administratorsOnly' => false,
				'grouping' => 'Main actions',
				'check' => 'userHasPageCreationRightsHere',
			),
			
			/*
			'subsectionTitle' => array (
				'title' => 'Subsection title',
				'tooltip' => '',
				'url' => $this->pureContentTitleFile . '?edit',
				'administratorsOnly' => false,
				'grouping' => 'Additional',
				'check' => 'userHasPageCreationRightsHere',
			),
			
			'sectionMenu' => array (
				'title' => 'Section menu',
				'tooltip' => '',
				#!# Add in the first directory at the start here
				'url' => $this->pureContentMenuFile . '?edit',
				'administratorsOnly' => false,
				'grouping' => 'Additional',
				'check' => 'userHasPageCreationRightsHere',
			),
			*/
			
			'permissionMine' => array (
				'title' => 'My areas',
				'tooltip' => 'List the areas which I have access to make changes to',
				'administratorsOnly' => false,
				'grouping' => 'Additional',
			),
			
			'showCurrent' => array (
				'title' => 'List pages here',
				'tooltip' => 'List the pages in the current section (folder) of the website',
				'administratorsOnly' => false,
				'grouping' => 'Additional',
			),
			
			'message' => array (
				'title' => 'Send message',
				'tooltip' => 'Send a message to the administrator and/or other users of the editing system',
				'administratorsOnly' => false,
				'grouping' => 'Additional',
			),
			
			/*'help' => array (
				'title' => 'Help',
				'tooltip' => '',
				'administratorsOnly' => false,
				'grouping' => 'Additional',
			),*/
			
			'logout' => array (
				'title' => 'Log out',
				'tooltip' => 'Log out when you have finished working with the editing system to secure your account',
				'administratorsOnly' => false,
				'grouping' => 'Additional',
			),
			
			'userList' => array (
				'title' => 'List users',
				'tooltip' => 'List the users who have access to the editing system',
				'administratorsOnly' => true,
				'grouping' => 'Users',
			),
			
			'userAdd' => array (
				'title' => 'Add a user',
				'tooltip' => 'Give another user access to the editing system',
				'administratorsOnly' => true,
				'grouping' => 'Users',
			),
			
			'userAmend' => array (
				'title' => 'Amend user',
				'tooltip' => "Amend aspects of the current user's account",
				'administratorsOnly' => true,
				'grouping' => 'Users',
			),
			
			'userRemove' => array (
				'title' => 'Remove a user',
				'tooltip' => 'Remove a current user from the list of those given access to the editing system',
				'administratorsOnly' => true,
				'grouping' => 'Users',
			),
			
			'permissionList' => array (
				'title' => 'List permissions',
				'tooltip' => 'List the permissions available to users of the editing system',
				'administratorsOnly' => true,
				'grouping' => 'Permissions',
			),
			
			'permissionGrant' => array (
				'title' => 'Grant permissions',
				'tooltip' => 'Give a registered user permission to make changes to a page, section or tree of pages',
				'administratorsOnly' => true,
				'grouping' => 'Permissions',
			),
			
			'permissionAmend' => array (
				'title' => 'Amend permissions',
				'tooltip' => "Amend aspects of a permission granted to a current user",
				'administratorsOnly' => true,
				'grouping' => 'Permissions',
			),
			
			'permissionRevoke' => array (
				'title' => 'Revoke permissions',
				'tooltip' => 'Remove a permission currently granted to a current user',
				'administratorsOnly' => true,
				'grouping' => 'Permissions',
			),
			
			'review' => array (
				'title' => 'Review submissions',
				'tooltip' => 'Review pages which have been edited by users',
				'administratorsOnly' => true,
				'grouping' => 'Reviewing',
			),
		);
		
		# Perform checks, mapping the flag 'administratorsOnly' onto a check for $this->userIsAdministrator
		foreach ($tasks as $task => $attributes) {
			if ($attributes['administratorsOnly']) {$attributes['check'] = 'userIsAdministrator';}
			if (isSet ($attributes['check'])) {
				if (!$this->{$attributes['check']}) {
					unset ($tasks[$task]);
				}
			}
		}
		
		
		# Return the tasks
		return $tasks;
	}
	
	
	# Function to ensure there is an authenticated user
	function userValid ()
	{
		# Ensure the user is in the list of allowed users
		if (!isSet ($this->users[$this->user])) {
			echo application::showUserErrors ('You are not in the list of allowed users and so have no access to the editing facility.</p>');
			return false;
		}
		
		# Otherwise return true
		return true;
	}
	
	
	# Wrapper function to deal with the changelog
	function logChange ($message)
	{
		# Prepend the message
		$message = $this->makeTimestamp () . ",{$this->user}," . $message . PHP_EOL;
		
		# Log the change
		if (!application::writeDataToFile ($message, $this->filestoreRoot . $this->changelog)) {
			$this->reportErrors ('There was a problem logging this change.', "The log file is at {$this->filestoreRoot}{$this->changelog} .");
		}
	}
	
	
	# Function to add an administrative menu
	function showMenu ()
	{
		# Group the tasks
		foreach ($this->tasks as $task => $attributes) {
			$grouping = $attributes['grouping'];
			$menu[$grouping][] = $task;
		}
		
		# Compile the menu HTML
		$html  = "\n\n<div id=\"administration\">";
		$html .= "\n\t<p><em>pureContentEditor</em> actions available here for <strong>{$this->user}</strong>:</p>";
		$html .= "\n\t<ul>";
		foreach ($menu as $group => $tasks) {
			$html .= "\n\t\t<li>{$group}:";
			$html .= "\n\t\t\t<ul>";
			foreach ($tasks as $task) {
				$html .= "\n\t\t\t\t<li" . (($task == $this->action) ? ' class="selected"' : '') . '><a href="' . (isSet ($this->tasks[$task]['url']) ? $this->tasks[$task]['url'] : "?$task") . '"' . ($this->tasks[$task]['administratorsOnly'] ? ' class="administrative"' : '') . " title=\"{$this->tasks[$task]['tooltip']}\">{$this->tasks[$task]['title']}</a></li>";
			}
			$html .= "\n\t\t\t</ul></li>";
		}
		$html .= "\n\t</ul>";
		$html .= "\n</div>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a version message
	function versionMessage ($action)
	{
		# Define the message
		$versionMessage  = '';
		switch ($this->pageToUse) {
			case 'staging':
				$versionMessage  = 'You are ' . str_replace ('browse', 'brows', "{$action}ing") . " a copy of a version of the page (saved at " . $this->convertTimestamp ($this->submissions[$this->stagingPage]['timestamp']) . " by " . $this->convertUsername ($this->submissions[$this->stagingPage]['username']) . ') which is currently <strong>not yet approved</strong> but is the latest version available.';
				if ($this->livePage) {$versionMessage .= " You may wish instead to <a href=\"{$this->page}?{$action}=original\">{$action} a copy of the version which is currently live</a>.";}
				break;
			case 'live':
				$versionMessage  = 'You are ' . str_replace ('browse', 'brows', "{$action}ing") . " a copy of the page which is currently <strong>live</strong>.";
				if ($this->stagingPage) {$versionMessage .= " You may wish instead to <a href=\"{$this->page}" . ($action == 'edit' ? "?$action" : '') . "\">{$action} a copy of the latest submitted (but unapproved) version</a>.";}
				break;
			case 'particular':
				# If the page is not the latest staging page available, provide a link to that
				if ($this->particularPage != $this->stagingPage) {
					$attributes = $this->submissions[$this->stagingPage];
					$location = $attributes['directory'] . $attributes['filename'];
					$versionMessage  = "<strong>A <a" . ($this->reviewPagesOpenNewWindow ? ' title="Link opens in a new window" target="_blank"': '') . " href=\"$location?review={$this->stagingPage}\">later version of this file</a> exists</strong>. ";
				}
				$versionMessage  .= 'You are ' . str_replace ('browse', 'brows', "{$action}ing") . " a copy of a version of the page (saved at " . $this->convertTimestamp ($this->submissions[$this->particularPage]['timestamp']) . " by " . $this->convertUsername ($this->submissions[$this->particularPage]['username']) . ') which is currently not yet approved.';
				# If there is a live page, provide a link to that too
				if ($this->livePage) {$versionMessage .= " You may wish instead to <a href=\"{$this->page}?{$action}=original\">{$action} a copy of the version which is currently live</a>.";}
				break;
		}
		
		# Construct the HTML
		$html = "\n<p class=\"information\">Note: $versionMessage</p>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a logout link
	function logout ()
	{
		# Create the logout link
		echo "\n<p class=\"information\">" . ($this->logoutlocation ? "<a href=\"{$this->logoutlocation}\">Please click here to log out.</a>" : 'To log out, close all instances of your web browser.') . '</p>';
	}
	
	
	# Function to create a help section
	function help ()
	{
		# Create the logout link
		echo "\n<h1>Help/about</h1>";
		echo "\n<p>To be written ...</p>";
		echo "\n<p>Popups must be enabled by your browser</p>";
	}
	
	
	# Function to load pages as if loading normally using pureContent
	function browse ()
	{
		# Define a message that it cannot be browsed for technical reasons
		$message = "\n<p class=\"information\">Note: for technical reasons, <strong>this page cannot be " . ($this->pageIsBeingAliased ? 'browsed or ' : '') . "edited</strong> using the pureContentEditor system, as it contains special processing instructions and so has to be treated with special care. " . ($this->pageIsBeingAliased ? "(Technically speaking, the page is being 'aliased' at server level.) " : '') . "Please <a href=\"{$this->page}?message\">contact the server administrator</a> to discuss making changes to this page.</p>";
		
		# If the page is being aliased, stop at this point
		if ($this->pageIsBeingAliased) {
			echo $message;
			return false;
		}
		
		# Give a message for what file is being browsed, including the timestamp
		echo $this->versionMessage (__FUNCTION__);
		
		# Check for the presence of PHP instructions or aliasing
		if ($this->pageContainsPhp) {
			
			# Give a message that the page cannot be edited with this system
			echo $message;
			
			# Change the working directory, in case there are local includes
			chdir (str_replace ('\\', '/', dirname ($this->editableFile)));
		}
		
		# Import the globals environment into local scope
		extract ($GLOBALS);
		
		# Show the contents
		echo "\n<hr />\n</div>\n\n\n";
		include ($this->editableFile);
		echo "\n\n\n<div>";
	}
	
	
	# Function to edit the page
	function edit ()
	{
		# Start the HTML, enclosing it in a div for CSS styling purposes, echoing it directly because of the form
		echo "\n\n<div id=\"editor\">";
		
		# Determine whether the user can make files live immediately
		#!# Add more options based on the permissions database
		$userCanMakeFilesLiveImmediately = $this->userIsAdministrator;
		
		# Create the form itself
		$form = new form (array (
			'developmentEnvironment' => $this->developmentEnvironment,
			'formName' => 'purecontent',
			'displayTitles' => ($this->typeOfFile == 'titleFile'),
			'displayDescriptions' => ($this->typeOfFile == 'titleFile'),
			'displayColons' => true,
			'submitButtonText' => 'Submit page for approval' . ($userCanMakeFilesLiveImmediately ? ' / Make live' : ''),
			'showFormCompleteText' => false,
			'nullText' => 'Select which administrator to inform of this submission:',
		));
		
		# Give a message for what file is being edited
		$form->heading ('', $this->versionMessage (__FUNCTION__));
		
		# Give the correct type of editing box
		switch ($this->typeOfFile) {
			
			# For a title file, show a standard input box
			case 'titleFile':
				$form->input (array (
					'elementName'			=> 'content',
					'title'					=> 'Title for the section (title file)',
					'elementDescription'	=> 'Please capitalise correctly. This is the text that will appear in the breadcrumb trail and must not be too long.',
					'initialValue'			=> $this->editableFileContents,
					'required'				=> true,
				));
				break;
				
			# For a menu or a normal page
			case 'menuFile':
			default:
				# Create the richtext field
				$form->richtext (array (
					'elementName'			=> 'content',
					'title'					=> 'Page content',
					'required'				=> true,
					'width'					=> $this->richtextEditorWidth,
					'height'				=> $this->richtextEditorHeight,
					'initialValue'			=> $this->editableFileContents,
					'editorBasePath'		=> $this->richtextEditorBasePath,
					'editorToolbarSet'		=> 'pureContent',
					'editorConfig'			=> array (
						'StartupFocus'			=> true,
						'EditorAreaCSS'			=> $this->richtextEditorEditorAreaCSS,
						#'BaseHref'				=> $this->currentDirectory,	// Doesn't work in FCKeditor 2.0 RC3
					),
				));
		}
		
		# Select the administrator to e-mail
		$form->select (array ( 
		    'elementName'            => 'administrators', 
		    'valuesArray'            => $this->administratorSelectionList ($enableNoneOption = $userCanMakeFilesLiveImmediately),
		    'title'                    => 'Administrator to inform', 
		    'minimumRequired'        => 1,
			'outputFormat'			=> array ('processing' => 'compiled'),
			'initialValues' => ($userCanMakeFilesLiveImmediately ? '_none' : '_all'),
		));
		
		# Allow administrators to make live immediately
		if ($userCanMakeFilesLiveImmediately) {
			$makeAdministratorText = 'Make live immediately (do not add to approval list)';
			$form->checkboxes (array (
			    'elementName'			=> 'preapprove', 
			    'valuesArray'			=> array ($makeAdministratorText,), 
			    'title'					=> $makeAdministratorText,
			));
		}
		
		# Finish the HTML
		echo "\n</div>";
		
		# Show and process the form; end if not submitted
		if (!$result = $form->processForm ()) {return false;}
		
		# Get the submitted content
		$content = (($this->typeOfFile == 'titleFile') ? $result['content'] : $this->editAdjustHtml ($result['content']));
		
		# Determine whether to approve immediately
		$approveImmediately = ($userCanMakeFilesLiveImmediately ? $result['preapprove'][$makeAdministratorText] : false);
		
		# Save the file to the filestore or the live site as appropriate
		if ($approveImmediately) {
			if (!$this->makeLive ($this->page, $content, $approveImmediately)) {
				return false;
			}
			$message = "A page has immediately been made live at {$this->liveSiteUrl}{$this->page} .";
		} else {
			
			# Create the file by supplying the complete file location and filename
			if (!$filename = application::createFileFromFullPath ($this->filestoreRoot . $this->page, $content, $addStamp = true)) {
				$this->reportErrors ('Unfortunately, the operation failed - there was a problem creating the new file in the filestore.', "This new file would have been at $this->page on the live site.");
				return false;
			} else {
				
				# Log the change
				$this->logChange ("Submitted {$this->page}");
				
				# Construct a confirmation message
				$message = "A page has been submitted for the location {$this->page} . Please log on to the editing system to moderate it, at:\n\n{$this->editSiteUrl}{$this->page}?review=" . ereg_replace ('^' . $this->filestoreRoot, '', $filename);
			}
		}
		
		# Delete the version on which the pre-edited page was based if it is based on a particular page (which, by definition, lives in the filestore)
		if ($this->particularPage) {
			if (!@unlink ($this->editableFile)) {
				$this->reportErrors ('There was a problem deleting the pre-edited page.', "The filename was {$this->editableFile} .");
				return false;
			}
			$this->logChange ("Pre-edited page $this->page deleted from filestore.");
			echo "\n<p class=\"success\">The pre-edited page from which this new page was created has been deleted from the filestore.</p>";
		}
		
		# Display the submitted content and its HTML version as a confirmation
		echo $this->showMaterial ($content);
		
		# Select which administrator(s) to e-mail
		if (isSet ($message)) {
			switch ($result['administrators']) {
				case '_none':
					break;
				case '_all':
					$this->sendMail ($this->administrators, $message);
					break;
				default:
					$this->sendMail ($result['administrators'], $message);
			}
		}
		
		# Delete the template if it is one
		if ($pageContentsIsTemplate = (md5 ($this->editableFileContents) == md5 ($this->newPageTemplate))) {
			if (!@unlink ($this->editableFile)) {
				$this->reportErrors ('There was a problem deleting the template file.', "The filename was {$this->editableFile} .");
				return false;
			}
			$this->logChange ("Template page $this->page deleted from filestore.");
			echo "\n<p class=\"success\">The template page from which this new page was created has been deleted from the filestore.</p>";
		}
	}
	
	
	# Function to show a page (with the HTML version after)
	function showMaterial ($content, $class = 'success')
	{
		# Construct the HTML
		$html  = "\n<p class=\"{$class}\">The submitted material was as follows:</p>";
		$html .= "\n<hr />";
		$html .= "\n</div>";
		$html .= "\n\n\n";
		$html .= ($this->typeOfFile != 'titleFile' ? $content : "<pre>$content</pre>");
		$html .= "\n\n\n" . '<div id="purecontenteditor">';	// Admittedly, using ID here is not strictly valid HTML, but it keeps the stylesheet simpler
		
		# Continue the HTML if not a title file
		if ($this->typeOfFile != 'titleFile') {
			$html .= "\n<hr />";
			$html .= "\n<p class=\"{$class}\">The submitted underlying HTML was as follows:</p>";
			$html .= "\n<hr />";
			$html .= "\n<pre>";
			$html .= ($this->wordwrapViewedSubmittedHtml ? wordwrap (htmlentities ($content)) : htmlentities ($content));
			$html .= "\n</pre>";
			$html .= "\n<hr />";
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a new section
	function section ()
	{
		# Get the current folders for the live and staging areas
		$currentFolders = $this->getCurrentFoldersHere ();
		
		# Form for the new folder
		$form = new form (array (
			'displayDescriptions'	=> true,
			'developmentEnvironment' => $this->developmentEnvironment,
			'displayRestrictions'	=> false,
			'showFormCompleteText'	=> false,
			'submitButtonText'		=> 'Create new section (folder)',
		));
		$form->heading ('', "
			<p>A new section (i.e. a folder) should be created when creating a new set of related pages (as distinct from a page within an existing topic area).</p>
			" . ($currentFolders ? '<p>Current folders are <a href="#currentfolders">listed below</a>.</p>' : '') . "
			<h2>Important guidelines/rules:</h2>
			<ul class=\"spaced\">
				<li>When creating new folders, only <strong>lowercase alphanumeric characters</strong> are allowed (spaces, underscores and hyphens are not); <strong>maximum&nbsp;{$this->maximumFileAndFolderNameLength}&nbsp;characters</strong>.</li>
				<li>It is important that you think about <strong>permanence</strong> and the need for a <strong>hierarchical structure</strong> when creating new folders. For instance, if creating a new section for an annual report, don't create a folder called annualreports" . date ('Y') . "; instead, create an 'annualreports' folder, then inside that create one called '" . date ('Y') . "'. This is then a hierarchical structure which will work in the longer term, without having to be changed.</li>
				<li>Make names <strong>guessable</strong> and <strong>self-explanatory</strong>, so <strong>avoid abbreviations</strong>, and choose words that tend towards the generic. If you can't think of a single word that describes the new section, it is acceptable in these few cases to run two words together, e.g. 'annualreports'.</li>
			</ul>
			<p>You are currently in the location: <strong><a href=\"{$this->currentDirectory}{$this->directoryIndex}\">{$this->currentDirectory}</a></strong></p>
		");
		$form->input (array (
			'elementName'			=> 'new',
			'title'					=> 'New section (folder) name',
			'elementDescription'	=> 'Please follow the guidelines above when entering the new folder name',
			'required'				=> true,
			'regexp'				=> "^[a-z0-9]{1,{$this->maximumFileAndFolderNameLength}}$",
		));
		
		$form->input (array (
			'elementName'			=> 'title',
			'elementDescription'	=> 'Please capitalise correctly. This is the text that will appear in the breadcrumb trail and must not be too long.',
			'title'					=> 'Title (for breadcrumb trail)',
			'required'				=> true,
		));
		
		# Show the form and get any results
		$result = $form->processForm ();
		
		# Show the folders which currently exist if there are any
		if (!$result) {
			echo $this->listCurrentResources ($currentFolders, 'folders');
			return false;
		}
		
		# Get the new folder location
		$new = $result['new'];
		
		# If the folder exists, show a warning message and do not proceed
		if (in_array ($new, $currentFolders)) {
			echo "\n<p class=\"failure\">Sorry, that folder name ($new) already <a href=\"$new/{$this->directoryIndex}\">exists</a>. Please <a href=\"{$this->page}?" . __FUNCTION__ . '">go back</a> and try again.</p>';
			return false;
		}
		
		# Create the directory, ensuring that / becomes \ on Windows
		$newDirectory = $this->filestoreRoot . $this->currentDirectory . $new . '/';
		if (strstr (PHP_OS, 'WIN')) {$newDirectory = str_replace ('/', '\\', $newDirectory);}
		if (!@mkdir ($newDirectory, 0775, $recursive = true)) {
			$this->reportErrors ('Unfortunately, the operation failed - there was a problem creating folders in the filestore; no index page or section title have been created either because of this.', "The proposed new directory was {$this->currentDirectory}{$new}/");
			return false;
		}
		
		# Log the change
		$this->logChange ("Created folder {$this->currentDirectory}{$new}/");
		
		# Create the title file
		$titleFileLocation = $newDirectory . $this->pureContentTitleFile;
		if (!application::createFileFromFullPath ($titleFileLocation, $result['title'], $addStamp = true)) {
			$this->reportErrors ('Unfortunately, the operation failed - there was a problem creating the title file in the filestore; the new index page has also not been created.');
			return false;
		}
		
		# Log the change
		$this->logChange ("Created title file {$this->currentDirectory}{$new}{$this->pureContentTitleFile}");
		
		# Create the front page
		$titleFileLocation = $newDirectory . $this->directoryIndex;
		if (!application::createFileFromFullPath ($titleFileLocation, $this->newPageTemplate, $addStamp = true)) {
			$this->reportErrors ('Unfortunately, the operation failed - there was a problem creating the new directory index in the filestore.');
			return false;
		}
		
		# Log the change
		$this->logChange ("Created template index page {$this->currentDirectory}{$new}{$this->directoryIndex}");
		
		# Notionally return true
		echo "<p class=\"success\">The new folder and title file were successfully created. You should now <a href=\"$new/{$this->directoryIndex}?edit\">edit the front page of this new section</a>.</p>";
		return true;
	}
	
	
	# Function to get the current folders here
	function getCurrentFoldersHere ()
	{
		# Get the live and staging folders
		$currentFoldersLive = directories::listContainedDirectories ($this->liveSiteRoot . $this->currentDirectory, $this->hideDirectoryNames);
		$currentFoldersStaging = directories::listContainedDirectories ($this->filestoreRoot . $this->currentDirectory, $this->hideDirectoryNames);
		
		# Merge, unique and sort the list
		$currentFolders = array_unique (array_merge ($currentFoldersLive, $currentFoldersStaging));
		sort ($currentFolders);
		
		# Return the list
		return $currentFolders;
	}
	
	
	# Function to get the current pages here
	function getCurrentPagesHere ()
	{
		# Limit the filetypes
		$supportedFileTypes = array ('html', 'txt');
		
		# Get the live and staging folders; a check is done first for whether it exists
		$currentFilesLive = array ();
		if (is_dir ($currentDirectoryLive = $this->liveSiteRoot . $this->currentDirectory)) {
			$currentFilesLive = directories::listFiles ($this->liveSiteRoot . $this->currentDirectory, $supportedFileTypes, $directoryIsFromRoot = true);
		}
		
		$currentFilesStaging = array ();
		foreach ($this->submissions as $submission => $attributes) {
			if ($attributes['directory'] == $this->currentDirectory) {
				$currentFilesStaging[$attributes['filename']] = $attributes;
			}
		}
		
		# Merge, unique and sort the list
		$currentFiles = array_merge ($currentFilesLive, $currentFilesStaging);
		$currentFiles = array_keys ($currentFiles);
		ksort ($currentFiles);
		
		# Return the list
		return $currentFiles;
	}
	
	
	# Function to show a current folder listing
	function listCurrentResources ($currentResources, $type = 'folders')
	{
		# Create a list if any exist
		if (!$currentResources) {return false;}
		
		# Add links to each, adding a slash if the resource type is folders
		foreach ($currentResources as $resource) {
			
			# Add a slash if the resource is the folder type
			if ($type == 'folders') {$resource .= '/';}
			
			# Do not list banned locations
			if ($this->matchLocation ($this->bannedLocations, $this->currentDirectory . $resource)) {continue;}
			
			# Add the item, correctly formatted
			$link = $resource . ($type == 'folders' ? $this->directoryIndex : '');
			$currentResourcesLinked[] = "<a href=\"$link\">$resource</a>";
		}
		
		# Construct and return the HTML
		return $html = "\n<p id=\"information\">The following are the $type which currently exist in this area:</p>" . application::htmlUl ($currentResourcesLinked);
	}
	
	
	# Function to create a new page
	function newPage ()
	{
		# Get the current pages for the live and staging areas
		$currentPages = $this->getCurrentPagesHere ();
		
		# Form for the new folder
		$form = new form (array (
			'displayDescriptions'	=> true,
			'developmentEnvironment' => $this->developmentEnvironment,
			'displayRestrictions'	=> false,
			'showFormCompleteText'	=> false,
			'submitButtonText'		=> 'Create new page',
			'submitTo' => "{$this->page}?" . __FUNCTION__,
		));
		
		# If there is no directory index, state that this is required
		if (!$this->directoryContainsIndex) {
			$form->heading ('', "<p class=\"information\">This section currently contains no front page ({$this->directoryIndex}). You are required to create one before proceeding further.</p>");
		}
		
		# Guideline text
		$form->heading ('', "
			<h2>Important guidelines/rules</h2>
			<ul class=\"spaced\">
				<li>When creating new pages, only <strong>lowercase alphanumeric characters</strong> are allowed (spaces, underscores and hyphens are not).</li>
				<li>The page name must <strong>end with .html</strong> .</li>
				<li>The total length (including the suffix .html) can be a <strong>maximum of {$this->maximumFileAndFolderNameLength} characters</strong>.</li>
				<li>It is important that you think about <strong>permanence</strong> when creating new pages. For instance, if creating a new page to hold phone numbers, don't create a page called 'phonenumbers'; instead, create a page called 'contacts' as that gives more flexibility in the long-run.</li>
				<li>Make names <strong>guessable</strong> and <strong>self-explanatory</strong>, so <strong>avoid abbreviations</strong>, and choose words that tend towards the generic. If you can't think of a single word that describes the new section, it is acceptable in these few cases to run two words together, e.g. 'annualreports'.</li>
			</ul>
			<p>You are currently in the location: <strong><a href=\"{$this->currentDirectory}{$this->directoryIndex}\">{$this->currentDirectory}</a></strong></p>
		");
		
		# Page name
		if (!$this->directoryContainsIndex) {
			$form->heading ('', "New page name: <strong>{$this->directoryIndex}</strong>");
			$form->hidden (array ('valuesArray' => array ( 'new' => $this->directoryIndex)));	// Hidden field is bogus
		} else {
			$form->input (array (
				'elementName'			=> 'new',
				'title'					=> 'New page name',
				'elementDescription'	=> 'Please follow the guidelines above when entering the new page name',
				'required'				=> true,
				'regexp'				=> "^[a-z0-9]{1,{$this->maximumFileAndFolderNameLength}}.html$",
			));
		}
		
		# Show the form and get any results
		$result = $form->processForm ();
		
		# Show the folders which currently exist if there are any
		if (!$result) {
			echo $this->listCurrentResources ($currentPages, 'pages');
			return false;
		}
		
		# Get the new folder location
		$new = ($this->directoryContainsIndex ? $result['new'] : $this->directoryIndex);
		
		# If the file exists, show a warning message and do not proceed
		if (in_array ($new, $currentPages)) {
			echo "\n<p class=\"failure\">Sorry, that page name ($new) already <a href=\"$new\">exists</a>. Please <a href=\"{$this->page}?" . __FUNCTION__ . '">go back</a> and try again.</p>';
			return false;
		}
		
		# Create the file
		$newFile = $this->currentDirectory . $new;
		if (!application::createFileFromFullPath ($this->filestoreRoot . $newFile, $this->newPageTemplate, $addStamp = true)) {
			$this->reportErrors ('Unfortunately, the operation failed - there was a problem creating the new file in the filestore.', "The filename was {$this->filestoreRoot}{$newFile} .");
			return false;
		}
		
		# Log the change
		$this->logChange ("Created template page {$newFile}");
		
		# Notionally return true
		echo "<p class=\"success\">The new file was successfully created. You should now <a href=\"$new?edit\">edit the new page</a>.</p>";
		return true;
	}
	
	
	# Function to show the pages in the current location
	function showCurrent ()
	{
		# Show the current location
		$html  = "\n<p class=\"information\">You are currently in the location: {$this->currentDirectory}</p>";
		
		# List the current pages
		$html .= "\n<h2>Sub-sections (folders) in this section</h2>";
		$currentFolders = $this->getCurrentFoldersHere ();
		$html .= ($currentFolders ? $this->listCurrentResources ($currentFolders, 'folders') : "\n<p>There are no folders in this area at present.</p>");
		$html .= "\n<p>You may wish to <a href=\"?section\">create a new section (folder)</a>" . ($currentFolders ? ' if there is not a relevant one already' : '') . '.</p>';
		
		# List the current pages
		$html .= "\n<h2>Pages in this section</h2>";
		$currentPages = $this->getCurrentPagesHere ();
		$html .= ($currentPages ? $this->listCurrentResources ($currentPages, 'pages') : "\n<p>There are no pages in this area at present.</p>");
		$html .= "\n<p>You may wish to <a href=\"?newPage\">create a new page</a>" . ($currentPages ? ' if there is not a relevant one already' : '') . '.</p>';
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to adjust the (already-cleaned) HTML
	function editAdjustHtml ($content)
	{
		# Define the replacements as an associative array
		$replacements = array (
			'<span>@</span>' => '<span>&#64;</span>',	// Replace e-mail addresses with anti-spambot equivalents
			'<a href="mailto:([^@]*)@([^"]*)">\1@\2</a>' => '\1<span>&#64;</span>\2',	// Replace e-mail addresses with anti-spambot equivalents
			' href="([^"]*)/' . $this->directoryIndex . '"'	=> ' href="\1/"',	// Chop off directory index links
			" href=\"{$this->editSiteUrl}/"	=> " href=\"{$this->liveSiteUrl}/",	// Ensure images are not prefixed with the edit site's URL
			" href=\"{$this->liveSiteUrl}/"	=> " href=\"/",	// Ensure images are not prefixed with the edit site's URL
			" src=\"{$this->liveSiteUrl}/"	=> 'src="/',	// Ensure images are not prefixed with the current site's URL
			"<(li|tr|/tr|tbody|/tbody)"	=> "\t<\\1",	// Indent level-two tags
			"<(td|/td)"	=> "\t\t<\\1",	// Indent level-three tags
		);
		
		# Ensure links to pages outside the page are in a new window
		if ($this->externalLinksTarget) {
			$replacements += array (
				'<a href="(http:|https:)//' => '<a target="' . $this->externalLinksTarget . '" href="\1//',
			);
		}
		
		# Replacement of image alignment with a similarly-named class
		if ($this->imageAlignmentByClass) {
			$replacements += array (
				'<img([^>]*) align="(left|center|centre|right)"([^>]*)>' => '<img\1 class="\2"\3>',
			);
		}
		
		# Perform the replacements
		foreach ($replacements as $find => $replace) {
			$content = eregi_replace ($find, $replace, $content);
		}
		
		# Return the adjusted content
		return $content;
	}
	
	
	# Function to list the users
	function userList ()
	{
		# Get the users from the CSV file
		$users = csv::getData ($this->userDatabase);
		
		# Change the administrator indication
		$usersFormatted = array ();
		foreach ($users as $user => $attributes) {
			$user = "<a href=\"?userAmend=$user\">$user</a>";
			$usersFormatted[$user] = $attributes;
			$usersFormatted[$user]['Administrator'] = ($attributes['Administrator'] ? 'Yes' : 'No');
		}
		
		# Show the table of current users
		echo "\n<p class=\"information\">The following are currently registered as users of the editing system. To edit a user's details, click on their username.</p>";
		echo application::dumpDataToTable ($usersFormatted);
	}
	
	
	# Function to add a user
	function userAdd ($firstRun = false)
	{
		# Create the form itself
		$form = new form (array (
			'developmentEnvironment' => $this->developmentEnvironment,
			'displayRestrictions' => false,
			'formName' => __FUNCTION__,
			'showFormCompleteText' => false,
			'submitTo' => "{$this->page}?" . __FUNCTION__,
		));
		
		# Add a heading for the first run
		if ($firstRun) {
			$form->heading ('', '<p class="information">There are currently no users. You are required to create a new administrative user on first login. Please enter your details.</p>');
		}
		
		# Widgets
		$form->input (array (
		    'elementName'            => 'Username', 
		    'title'                    => "New user's username", 
			'elementDescription' =>  "Usernames can only have lower-case alphanumeric characters and must be at least {$this->minimumUsernameLength} " . ($this->minimumUsernameLength == 1 ? 'character' : 'characters') . ' in length',
		    'required'                => true, 
		    'size'                => 10, 
		    'maxlength'                => 10, 
			'initialValue' => ($firstRun ? $this->user : ''),
			'regexp'				=> "^[a-z0-9]{{$this->minimumUsernameLength},}$",
		));
		$form->email (array (
		    'elementName'            => 'E-mail', 
		    'title'                    => 'E-mail address', 
		    'required'                => true, 
		));
		$form->input (array (
		    'elementName'            => 'Forename', 
		    'title'                    => 'Forename', 
		    'required'                => true, 
		));
		$form->input (array (
		    'elementName'            => 'Surname', 
		    'title'                    => 'Surname', 
		    'required'                => true, 
		));
		$form->textarea ($this->additionalMessageWidget);
		if (!$firstRun) {
			$makeAdministratorText = 'Make administrator';
			$form->checkboxes (array (
			    'elementName'            => 'Administrator', 
			    'valuesArray'            => array ($makeAdministratorText,), 
			    'title'                    => 'Grant administrative rights?',
				'elementDescription' =>  'Warning! This will give the right to approve pages, grant new users, etc.',
			));
		}
		
		# Show the form and get any results or end here
		if (!$result = $form->processForm ()) {return;}
		
		# If the user is already in the database, end here
		if (isSet ($this->users[$result['Username']])) {
			echo "\n<p class=\"failure\">The user {$result['Username']} already exists and so was not added.</p>";
			return false;
		}
		
		# Flatten the checkbox result
		$result['Administrator'] = ($firstRun ? true : ($result['Administrator']['Make administrator'] ? '1' : '0'));
		
		# Arrange the array into a keyed result
		$newUser[$result['Username']] = $result;
		
		# Insert the data into the CSV file
		if (!csv::addItem ($this->userDatabase, $newUser, $this->databaseTimestampingMode)) {return false;}
		
		# Log the change
		$this->logChange ("Created new user {$result['Username']} with " . ($result['Administrator'] ? 'administrative' : 'editing') . " rights");
		
		# Signal success, firstly reloading the database
		$this->users = $this->users ();
		echo "\n<p class=\"success\">The user {$result['Username']} was successfully added.</p>";
		$this->sendMail ($result['Username'], "You now have access to the website editing facility. You can log into the pureContentEditor system at {$this->editSiteUrl}/ , using your Raven username and password. You are recommended to bookmark that address in your web browser.\n\nYou will be separately advised of the area(s) of the site which you have permission to alter." . ($result['message'] ? "\n\n{$result['message']}" : ''));
		echo "<p><a href=\"{$this->page}\"><strong>Click here to continue.</strong></a></p>";
	}
	
	
	# Function to amend a user's details
	function userAmend ()
	{
		# Get the username (if supplied)
		$username = $this->attributes;
		
		# If a user has been selected but does not exist, say so
		if ($username && !isSet ($this->users[$username])) {
			echo "\n<p class=\"failure\">There is no user {$this->attributes}.</p>";
		}
		
		# Show the list of users with the links if no user has been selected
		if (!$username || !isSet ($this->users[$username])) {
			$this->userList ();
			return false;
		}
		
		# Create the form itself
		$form = new form (array (
			'developmentEnvironment' => $this->developmentEnvironment,
			'displayRestrictions' => false,
			'formName' => __FUNCTION__,
			'showFormCompleteText' => false,
			'submitTo' => "{$this->page}?" . __FUNCTION__ . "=$username",
		));
		
		# Make the username non-editable (NB the spaces are a layout cheat)
		$form->heading ('', "Existing user's username:&nbsp;&nbsp;&nbsp;&nbsp;<strong>$this->attributes</strong>");
		$form->hidden (array ( 
		    'valuesArray'            => array ( 'Username' => $username, ), 
		    'outputFormat'            => array (), 
		    'title'                    => "Existing user's username:", 
		));
		$form->email (array (
		    'elementName'            => 'E-mail', 
		    'title'                    => 'E-mail address', 
		    'required'                => true, 
			'initialValue' => $this->users[$username]['E-mail'],
		));
		$form->input (array (
		    'elementName'            => 'Forename', 
		    'title'                    => 'Forename', 
		    'required'                => true, 
			'initialValue' => $this->users[$username]['Forename'],
		));
		$form->input (array (
		    'elementName'            => 'Surname', 
		    'title'                    => 'Surname', 
		    'required'                => true, 
			'initialValue' => $this->users[$username]['Surname'],
		));
		
		# If the current user selected themselves as a user, do not allow them to demote themselves
		if ($this->user == $username) {
			$form->heading ('', '(Administrators cannot demote themselves from being an administrator.)');
		} else {
			$makeAdministratorText = 'Make administrator';
			$form->checkboxes (array (
			    'elementName'            => 'Administrator', 
			    'valuesArray'            => array ($makeAdministratorText,), 
			    'title'                    => 'Grant administrative rights?',
				'elementDescription' =>  'Warning! This will give the right to approve pages, grant new users, etc.',
				'initialValues' => ($this->users[$username]['Administrator'] ? $makeAdministratorText : ''),
			));
		}
		
		# Additional message
		$form->textarea ($this->additionalMessageWidget);
		
		# Show the form and get any results or end here
		if (!$result = $form->processForm ()) {return;}
		
		# Convert the hidden field
		$result['Username'] = $result['hidden']['Username'];
		unset ($result['hidden']);
		
		# Ensure the username matches the posted one
		if ($result['Username'] != $username) {
			echo "\n<p class=\"failure\">The selected username does not match the one posted in the form.</p>";
			return false;
		}
		
		# Flatten the checkbox result
		$result['Administrator'] = (($this->user == $username) ? true : ($result['Administrator']['Make administrator'] ? '1' : '0'));
		
		# Arrange the array into a keyed result
		$user[$username] = $result;
		
		# Replace the data in the CSV file (add performs replacement when the key already exists)
		if (!csv::addItem ($this->userDatabase, $user, $this->databaseTimestampingMode)) {return false;}
		
		# Signal success
		echo "\n<p class=\"success\">The user {$result['Username']}'s details have been successfully updated.</p>";
		
		# Log the change
		$this->logChange ("Amended user details for {$result['Username']} with " . ($result['Administrator'] ? 'administrative' : 'editing') . " rights");
		
		# Flag changes of administrative status, reloading the database at this point
		if ($this->users[$username]['Administrator'] != $result['Administrator']) {
			echo "\n<p class=\"success\">The user " . ($result['Administrator'] ? 'now' : 'no longer') . ' has administrative rights.</p>';
			$message = 'You ' . ($result['Administrator'] ? 'now' : 'no longer') . ' have administrative rights.' . ($result['message'] ? "\n\n{$result['message']}" : '');
			$this->users = $this->users ();
			$this->sendMail ($username, $message);
		}
	}
	
	
	# Function to remove a user; there will always be one administrator remaining (the current user) as administrative privileges are required to use this function
	function userRemove ()
	{
		# Create a list of users with unapproved submissions
		$this->usersWithUnapprovedSubmissions = $this->usersWithUnapprovedSubmissions ();
		
		# Get the list of users
		$deletableUsers = $this->userSelectionList ($excludeUsersWithUnapprovedSubmissions = true, $excludeCurrentUser = true);
		
		# Prevent the form display if there are no users
		if (!$deletableUsers) {
			echo $message = "\n" . '<p class="information">' . ($this->usersWithUnapprovedSubmissions ? 'There remain' : 'There are') . ' no users available for deletion.</p>';
			echo ($this->usersWithUnapprovedSubmissions ? "<p class=\"information\">(Users having <a href=\"{$this->page}?review\">submissions awaiting approval</a> (which must be approved/deleted first) cannot be deleted.)</p>" : '');
			return;
		}
		
		# Create the form itself
		$form = new form (array (
			'developmentEnvironment' => $this->developmentEnvironment,
			'displayRestrictions' => false,
			'formName' => __FUNCTION__,
			'showFormCompleteText' => false,
			'submitTo' => "{$this->page}?" . __FUNCTION__,
		));
		
		# Determine whether there are approvals outstanding
		if ($this->usersWithUnapprovedSubmissions) {
			$form->heading ('', "<p class=\"information\">Note: some users are not listed as they have <a href=\"{$this->page}?review\">submissions awaiting approval</a>, which must be approved/deleted first.</p>");
		}
		
		# Widgets
		$form->heading ('', "<p>Note: deleting a user will also revoke any permissions they have.</p>");
		$form->select (array ( 
		    'elementName'            => 'username', 
		    'valuesArray'            => $deletableUsers, 
		    'title'                    => 'User to delete', 
		    'minimumRequired'        => 1, 
			'outputFormat'			=> array ('processing' => 'compiled'),
		));
		$form->input (array (
		    'elementName'            => 'confirmation', 
		    'title'                    => 'Confirm username',
			'elementDescription'	=> 'Please type in the username for confirmation',
		    'required'                => true, 
		));
		$form->textarea ($this->additionalMessageWidget);
		
		# Show the form and get any results or end here
		if (!$result = $form->processForm ()) {return;}
		
		# If there is no username match, force re-entry
		if ($result['username'] != $result['confirmation']) {
			echo "\n<p class=\"failure\">The usernames you entered did not match. Please <a href=\"{$this->page}?" . __FUNCTION__ . '">go back</a> and try again.</p>';
			return false;
		}
		
		# Check that there is such a user
		if (!array_key_exists ($result['username'], $this->users)) {
			echo "\n<p class=\"failure\">There is no such user to delete. (Perhaps you have just deleted the user and then refreshed this page accidentally?)</p>";
			return false;
		}
		
		# Create a list of the user's permissions
		$permissions = array ();
		foreach ($this->permissions as $key => $attributes) {
			if ($attributes['Username'] == $result['username']) {
				$permissions[] = $key;
			}
		}
		
		# Delete the permissions if there are any
		if ($permissions) {
			if (!csv::deleteData ($this->permissionsDatabase, $permissions, $this->databaseTimestampingMode)) {
				$this->reportErrors ('Unfortunately, the operation failed - there was a problem deleting their permissions; the attempt to delete user themselves has therefore been cancelled.');
				return false;
			}
		}
		
		# Log the change
		$this->logChange ("Deleted all permissions for user {$result['username']}");
		
		# Delete the user
		if (!csv::deleteData ($this->userDatabase, $result['username'], $this->databaseTimestampingMode)) {
			$this->reportErrors ('Unfortunately, the operation failed - there was a problem deleting the user, although any permissions were deleted successfully.');
			return false;
		}
		
		# Log the change
		$this->logChange ("Deleted user {$result['username']}");
		
		# Signal success then show the new list of users
		echo "\n<p class=\"success\">The user {$result['username']}" . ($permissions ? ' and their permissions were' : ' was') . " successfully deleted.</p>";
		$this->sendMail ($result['username'], 'You no longer have access to the editing system.' . ($result['message'] ? "\n\n{$result['message']}" : ''));
		$this->userList ();
		return true;
	}
	
	# Function to get the number of users with unapproved submissions
	function usersWithUnapprovedSubmissions ()
	{
		# Exclude users with unapproved submissions if necessary
		$usersWithUnapprovedSubmissions = array ();
		foreach ($this->submissions as $file => $attributes) {
			$usersWithUnapprovedSubmissions[$attributes['username']] = $attributes['username'];
		}
		
		# Return the list
		return $usersWithUnapprovedSubmissions;
	}
	
	
	# Function to create a userlist
	function userSelectionList ($excludeUsersWithUnapprovedSubmissions = false, $excludeCurrentUser = false, $excludeAdministrators = false)
	{
		# Compile the user list, excluding users with unapproved submissions and/or administrators if necessary
		$users = array ();
		foreach ($this->users as $user => $attributes) {
			
			# Skip users with unapproved submissions
			if ($excludeUsersWithUnapprovedSubmissions && in_array ($user, $this->usersWithUnapprovedSubmissions)) {continue;}
			
			# Skip administrators if necessary
			if ($excludeAdministrators && in_array ($user, $this->administrators)) {continue;}
			
			# Skip the current user if necessary
			if ($excludeCurrentUser && ($user == $this->user)) {continue;}
			
			# Add the user to the list
			$users[$user] = "$user: {$attributes['Forename']} {$attributes['Surname']}" . ($attributes['Administrator'] ? ' (Administrator)' : '');
		}
		
		# Return the userlist
		return $users;
	}
	
	
	# Function to create an administrator userlist
	function administratorSelectionList ($enableNoneOption = false, $excludeCurrentUser = true)
	{
		# Add all administrators or no administrators if required
		if ($enableNoneOption) {$users['_none'] = 'Inform no administrators';}
		$users['_all'] = 'Inform all administrators';
		
		# Compile the user list, excluding users with unapproved submissions and/or administrators if necessary
		foreach ($this->administrators as $user) {
			
			# Obtain the user credentials
			$attributes = $this->users[$user];
			
			# Skip the current user if necessary
			if ($excludeCurrentUser && ($user == $this->user)) {continue;}
			
			# Add the user to the list
			$users[$user] = "Inform administrator {$attributes['Forename']} {$attributes['Surname']} ($user)";
		}
		
		# Return the userlist
		return $users;
	}
	
	
	# Function to create a list of permissions available
	function scopeSelectionList ()
	{
		# Compile the permissions list 
		$permissions = array ();
		if ($this->permissions) {
			foreach ($this->permissions as $key => $attributes) {
				$userAttributes = $this->users[$attributes['Username']];
				$permissions[$key] = "{$attributes['Username']} ({$userAttributes['Forename']} {$userAttributes['Surname']}): " . $attributes['Location'];
			}
		}
		
		# Return the userlist
		return $permissions;
	}
	
	
	# Function to list the users
	function permissionList ()
	{
		# If there are no permissions assigned, say so
		if (!$this->permissions) {
			echo "\n<p class=\"information\">There are no permissions assigned (other than universal permissions available to administrators). You may wish to <a href=\"{$this->page}?permissionGrant\">grant some permissions</a>.</p>";
			return;
		}
		
		# Start a table of data; NB This way is better in this instance than using dumpDataToTable (), as the data contains HTML which will have htmlentities () applied;
		$html  = "\n<p>The following permissions are currently assigned:</p>";
		$html .= "\n" . '<table class="lines">';
		$html .= "\n\t" . '<tr>';
		$html .= "\n\t\t" . '<th>Amend?</th>';
		$html .= "\n\t\t" . '<th>User:</th>';
		$html .= "\n\t\t" . '<th>Can make changes to:</th>';
		$html .= "\n\t\t" . '<th>Date limitation?</th>';
/*
		$html .= "\n\t\t" . '<th>Can make pages live immediately?</th>';
*/
		$html .= "\n\t" . '</tr>';
		
		# Loop through each file to create the table
		foreach ($this->permissions as $permission => $attributes) {
			
			# Create a table row
			$html .= "\n\t" . '<tr>';
			$html .= "\n\t\t" . '<td>' . "<a href=\"?permissionAmend=$permission\">[Change]</a>" . '</td>';
			$html .= "\n\t\t" . '<td>' . $this->convertUsername ($attributes['Username']) . '</td>';
			$html .= "\n\t\t" . '<td>' . $this->convertPermission ($attributes['Location'], $descriptions = false) . '</td>';
			$html .= "\n\t\t" . '<td>' . $this->formatDateLimitation ($attributes['Startdate'], $attributes['Enddate']) . '</td>';
/*
			$html .= "\n\t\t" . '<td>' . ($attributes['Self-approval'] ? 'Yes' : 'No') . '</td>';
*/
			$html .= "\n\t" . '</tr>';
		}
		$html .= "\n" . '</table>';
		
		# Show the list
		echo $html;
	}
	
	
	/*
	# Function to chop the directory index off a location
	function chopDirectoryIndex ($location)
	{
		# Return the value
		return ereg_replace ("/{$this->directoryIndex}\$", '/', $location);
	}
	*/
	
	
	# Function to grant permission to a user
	function permissionGrant ()
	{
		# Determine the available users to which permissions are available to be granted (i.e. all except administrators)
		$users = $this->userSelectionList (false, false, $excludeAdministrators = true);
		
		# If there are no users available, say so
		if (!$users) {
			echo "\n<p class=\"information\">There are no non-administrative users, so no permissions can be granted. You may wish to <a href=\"{$this->page}?userAdd\">add a user</a>.</p>";
			return;
		}
		
		# Determine the scopes, the last being the default
		$scopes = array (
			$this->page => 'This page only',
			$this->currentDirectory => 'Pages in this section',
			$this->currentDirectory . '*' => 'Pages in this section and any subsections',
		);
		
		# Compile the scopes list and the last in the list
		foreach ($scopes as $scope => $description) {
			$scopeList[$scope] = "$description - $scope";
		}
		$last = $scope;
		
		# Create the form itself
		$form = new form (array (
			'developmentEnvironment' => $this->developmentEnvironment,
			'displayRestrictions' => false,
			'formName' => __FUNCTION__,
			'showFormCompleteText' => false,
			'displayDescriptions' => false,
			'submitTo' => "{$this->page}?" . __FUNCTION__,
			'nullText' => 'Please select',
		));
		
		# Do not include administrators, as they do not need permissions
		$form->select (array ( 
		    'elementName'            => 'username', 
		    'valuesArray'            => $users, 
		    'title'                    => 'Allow user', 
		    'minimumRequired'        => 1,
			'outputFormat'			=> array ('processing' => 'compiled'),
		));
		$form->select (array ( 
		    'elementName'            => 'scope', 
		    'valuesArray'            => $scopeList,
		    'title'                    => 'Allow changes to', 
		    'minimumRequired'        => 1, 
		    'initialValues'        => $last,
			'outputFormat'			=> array ('processing' => 'compiled'),
		));
/*
		$form->checkboxes (array (
		    'elementName'            => 'Self-approval', 
		    'valuesArray'            => array ($selfapprovalText = 'User can make pages live immediately'), 
		    'title'                    => 'Allow user to make pages live immediately',
		));
*/
		$form->datetime (array ( 
		    'elementName'            => 'Startdate', 
		    'title'                    => 'Optional availability start date', 
		    'level'                    => 'date', 
		)); 
		$form->datetime (array ( 
		    'elementName'            => 'Enddate', 
		    'title'                    => 'Optional availability end date', 
		    'level'                    => 'date', 
		)); 
		$form->textarea ($this->additionalMessageWidget);
		
		# Show the form and get any results or end here
		if (!$result = $form->processForm ()) {return;}
		
		# If the key is already in the database, end here
		$key = "{$result['username']}:{$result['scope']}";
		if (isSet ($this->permissions[$key])) {
			echo "\n<p class=\"failure\">The permission for {$result['username']} to amend {$result['scope']} already exists and so was not added.</p>";
			return false;
		}
		
		# Check the start and end dates
		if (!$this->checkStartEndDate ($result['Startdate'], $result['Enddate'])) {
			return false;
		}
		
		# Arrange the array
		$newPermission[$key] = array (
			'Key' => $key,
			'Username' => $result['username'],
			'Location' => $result['scope'],
			'Startdate' => $result['Startdate'],
			'Enddate' => $result['Enddate'],
/*
			'Self-approval' => $result['Self-approval'][$selfapprovalText],
*/
			'Self-approval' => 0,
		);
		
		# Insert the data into the CSV file
		if (!csv::addItem ($this->permissionsDatabase, $newPermission, $this->databaseTimestampingMode)) {return false;}
		
		# Log the change
		$this->logChange ("Granted user {$result['username']} permission to edit {$result['scope']} " . ($result['Startdate'] ? "from {$result['Startdate']} to {$result['Enddate']}" : 'no time limitation')/* . ($result['Startdate'] && $result['Self-approval'][$selfapprovalText] ? ' with ' : '') . ($result['Self-approval'][$selfapprovalText] ? 'self-approval allowed' : 'self-approval not allowed')*/);
		
		# Construct a time limitation notice
		$timeLimitationMessage = ($result['Startdate'] ? "\n\nYou can make changes between: " . $this->convertTimestamp ($result['Startdate'], $includeTime = false) . ' and ' . $this->convertTimestamp ($result['Enddate'], $includeTime = false) . '.' : '');
		
		# Signal success
		echo "\n<p class=\"success\">The permission {$result['scope']} for the user {$result['username']} was successfully added.</p>";
		$this->sendMail ($result['username'], "You have been granted permission to make changes to " . $this->convertPermission ($scope, $descriptions = true, $addLinks = false, $lowercaseStart = true) . ".\n\nThis means that when you are in that area of the website, you will see an additional button marked 'edit this page' when editing is allowed.". $timeLimitationMessage . ($result['message'] ? "\n\n{$result['message']}" : ''));
		return true;
	}
	
	
	# Function to check the start and end date
	function checkStartEndDate ($startDate, $endDate)
	{
		# Perform checks on the dates if either are given
		if ($startDate || $endDate) {
			
			# Ensure that if either of a start/end date have been given, that both are given
			if (!$startDate || !$endDate) {
				echo "\n<p class=\"failure\">You have specified one of the start/end date but not the other. Please go back and correct this.</p>";
				return false;
			}
			
			# Check that the start date comes before the end date; NB the >= seems to work successfully with comparison of strings including the dash (-) character
			if ($startDate >= $endDate) {
				echo "\n<p class=\"failure\">The end date must be after the start date. Please go back and correct this.</p>";
				return false;
			}
			
			# Check that the start (and thereby the end date) are after the current date
			if ($startDate < date ('Y-m-d')) {
				echo "\n<p class=\"failure\">The start/end dates cannot be retrospective. Please go back and correct this.</p>";
				return false;
			}
		}
		
		# Otherwise return true
		return true;
	}
	
	
	# Function to amend a user's details
	function permissionAmend ()
	{
		# Get the username (if supplied)
		$permission = $this->attributes;
		
		# If a user has been selected but does not exist, say so
		if ($permission && !isSet ($this->permissions[$permission])) {
			echo "\n<p class=\"failure\">There is no permission {$this->attributes}.</p>";
		}
		
		# Show the list of users with the links if no user has been selected
		if (!$permission || !isSet ($this->permissions[$permission])) {
			$this->permissionList ();
			return false;
		}
		
		# Create the form itself
		$form = new form (array (
			'developmentEnvironment' => $this->developmentEnvironment,
			'displayRestrictions' => false,
			'formName' => __FUNCTION__,
			'showFormCompleteText' => false,
			'submitTo' => "{$this->page}?" . __FUNCTION__ . "=$permission",
		));
		
		# Make the permission non-editable (NB the spaces are a layout cheat)
		list ($username, $scope) = explode (':', $permission);
		$form->heading ('', "Permission for: &nbsp;&nbsp;&nbsp;&nbsp;<strong>{$username}</strong> to change <strong>{$scope}</strong>");
		$form->hidden (array ( 
		    'valuesArray'            => array ( 'Permission' => $permission, ), 
		    'outputFormat'            => array (), 
		    'title'                    => "Existing permission:", 
		));
/*
		$selfapprovalText = 'User can make pages live immediately';
		$form->checkboxes (array (
		    'elementName'            => 'Self-approval', 
		    'valuesArray'            => array ($selfapprovalText, ), 
		    'title'                    => 'Allow user to make pages live immediately',
			'initialValues'            => ($this->permissions[$permission]['Self-approval'] ? $selfapprovalText : ''),
		));
*/
		$form->datetime (array ( 
		    'elementName'            => 'Startdate', 
		    'title'                    => 'Optional availability start date', 
		    'level'                    => 'date',
			'initialValue'            => $this->permissions[$permission]['Startdate'], 
		)); 
		$form->datetime (array ( 
		    'elementName'            => 'Enddate', 
		    'title'                    => 'Optional availability end date', 
		    'level'                    => 'date',
			'initialValue'            => $this->permissions[$permission]['Enddate'], 
		)); 
		$form->textarea ($this->additionalMessageWidget);
		
		# Show the form and get any results or end here
		if (!$result = $form->processForm ()) {return;}
		
		# Ensure the username matches the posted one
		if ($result['hidden']['Permission'] != $permission) {
			echo "\n<p class=\"failure\">The selected permission does not match the one posted in the form.</p>";
			return false;
		}
		
		# Check the start and end dates
		if (!$this->checkStartEndDate ($result['Startdate'], $result['Enddate'])) {
			return false;
		}
		
		# Arrange the array into a keyed result
		list ($result['Username'], $result['Location']) = explode (':', $result['hidden']['Permission']);
		$amendedPermission[$permission] = array (
			'Key' => $permission,
			'Username' => $result['Username'],
			'Location' => $result['Location'],
			'Startdate' => $result['Startdate'],
			'Enddate' => $result['Enddate'],
/*
			'Self-approval' => $result['Self-approval'][$selfapprovalText],
*/
			'Self-approval' => 0,
		);
		
		# Replace the data in the CSV file (add performs replacement when the key already exists)
		if (!csv::addItem ($this->permissionsDatabase, $amendedPermission, $this->databaseTimestampingMode)) {return false;}
		
		# Flag changes of administrative status, reloading the database at this point
		$this->permissions[$permission]['Key'] = $permission;
		if (($this->permissions[$permission] == $amendedPermission[$permission])) {
			echo "\n<p class=\"information\">No changes have been made to the permission for {$result['Username']} to change {$result['Location']}.</p>";
		} else {
			
			# Determine what has changed
			if ($dateHasChanged = ($this->permissions[$permission]['Startdate'] . $this->permissions[$permission]['Enddate'] != $amendedPermission[$permission]['Startdate'] . $amendedPermission[$permission]['Enddate'])) {
				$dateNowEmpty = ($amendedPermission[$permission]['Startdate'] . $amendedPermission[$permission]['Enddate'] == '');
			}
			$selfApprovalHasChanged = ($this->permissions[$permission]['Self-approval'] != $amendedPermission[$permission]['Self-approval']);
			
			# Log the change
			$this->logChange ("Amended permission details for {$permission} " . ($dateHasChanged ? (!$dateNowEmpty ? "now time-limited from {$result['Startdate']} to {$result['Enddate']}" : 'now no time limitation') : '') . ($dateHasChanged && $selfApprovalHasChanged ? ' and ' : '') . ($selfApprovalHasChanged ? ($amendedPermission[$permission]['Self-approval'] ? 'self-approval now allowed' : 'self-approval no longer allowed') : ''));
			
			# Show an on-screen message
			echo "\n<p class=\"success\">Changes have been made to the permission for {$result['Username']} to change {$result['Location']}.</p>";
			
			# Construct the e-mail message and send it
			$message =
				"Your permission to change {$result['Location']} has been amended and is now as follows:"
				. ($dateHasChanged ? "\n\n- " . (!$dateNowEmpty ? "You can make changes between: " . $this->convertTimestamp ($result['Startdate'], $includeTime = false) . ' and ' . $this->convertTimestamp ($result['Enddate'], $includeTime = false) . '.' : 'You no longer have limitations on when you can make changes.') : '')
				. ($selfApprovalHasChanged ? "\n\n- " . ($amendedPermission[$permission]['Self-approval'] ? 'You can now choose to make pages live immediately.' : 'You no longer have the option of making pages live immediately - pages require administrator approval.') : '')
				. ($result['message'] ? "\n\n{$result['message']}" : '');
			$this->sendMail ($username, $message);
		}
	}
	
	
	# Function to list the current user's permissions
	function permissionMine ()
	{
		# If the user is an administrator, state that they have universal permission
		if ($this->userIsAdministrator) {
			echo "\n<p class=\"success\">As you are an administrator, you have editable access across the site rather than access to particular areas.</p>";
			return;
		}
		
		# If no permissions, say so
		if (!$this->currentUserPermissions) {
			echo "\n<p>Although you have access to this facility as a whole, you do not currently have permission to edit any areas of the site.</p>";
			return;
		}
		
		# Convert the permissions to a human-readable form
		$currentUserPermissions = $this->convertPermissionsList ($this->currentUserPermissions);
		
		# Show the permissions
		echo "\n<p>You have permission to make changes to the following at present:</p>" . application::htmlUl ($currentUserPermissions);
	}
	
	
	# Function to get the current user's permissions; note this does not deal with the special case of administrators
	function currentUserPermissions ()
	{
		# Get the permissions
		$permissions = array ();
		foreach ($this->permissions as $permission => $attributes) {
			if ($attributes['Username'] == $this->user) {
				
				# Skip if a start/end date is specified but today's date is outside the period allowed
				if ($attributes['Startdate'] && $attributes['Enddate']) {
					$today = date ('Y-m-d');
					if (($today < $attributes['Startdate']) || ($today > $attributes['Enddate'])) {
						continue;
					}
				}
				
				# Assign the permission
				$permissions[] = $permission;
			}
		}
		
		# Return the permissions
		return $permissions;
	}
	
	
	# Function to convert a list of permissions into a list of areas
	function convertPermissionsList ($permissions)
	{
		# Loop through the permissions
		$readablePermissions = array ();
		foreach ($permissions as $permission) {
			$location = $this->permissions[$permission]['Location'];
			$readablePermissions[$location] = $this->convertPermission ($location);
		}
		
		# Return the list
		return $readablePermissions;
	}
	
	
	# Function to convert a single permission
	function convertPermission ($location, $descriptions = true, $addLinks = true, $lowercaseStart = false)
	{
		# Get the title file if relevant
		$sectionTitle = $this->getSectionTitle ($location);
		$sectionTitleHtml = ($sectionTitle ? " [$sectionTitle]" : '');
		
		# Create the customisations for each type
		$endingString = '';
		$star = '';
		switch (substr ($location, -1)) {
			case '*':
				$startingString = 'pages in the section';
				$endingString = ' and any subsections';
				$star = '*';
				break;
			case '/':
				$startingString = 'pages in the section';
				break;
			default:
				$startingString = 'the page';
		}
		
		# Chop off the asterisk if present
		if (substr ($location, -1) == '*') {$location = substr ($location, 0, -1);}
		
		# Format the string, uppercasing the start where necessary
		switch ($descriptions) {
			case true:
				$string = ($lowercaseStart ? $startingString : ucfirst ($startingString)) . ' ' . ($addLinks ? "<a href=\"$location\">" : '') . "{$location}{$sectionTitleHtml}" . ($addLinks ? '</a>' : '') . $endingString;
				break;
				
			case false:
				$string = ($addLinks ? "<a href=\"{$location}\" title=\"" . ($lowercaseStart ? $startingString : ucfirst ($startingString)) . " {$location}{$endingString}\">" : '') . "{$location}{$star}{$sectionTitleHtml}" . ($addLinks ? '</a>' : '');
				break;
		}
		
		# Return the constructed string
		return $string;
	}
	
	
	# Function to reformat a date limitation
	function formatDateLimitation ($start, $end)
	{
		# If no start and end, return an empty string
		if (!$start && !$end) {return '';}
		
		# Otherwise construct the string
		return $this->formatSqlDate ($start) .  ' to<br />' . $this->formatSqlDate ($end);
	}
	
	
	# Function to reformat a date in SQL format
	function formatSqlDate ($date)
	{
		# Attempt to split out the year, month and date
		if (!list ($year, $month, $day) = explode ('-', $date)) {return $date;}
		
		# Else return the full date, with the date and month formatted sensibly
		$months = array (1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',);
		return (int) $day . '/' . $months[(int) $month] . "/$year";
	}
	
	
	# Function to get contents of the title file for a section
	function getSectionTitle ($location)
	{
		# Chop off the * if necessary
		if (substr ($location, -1) == '*') {$location = substr ($location, 0, -1);}
		
		# If the location is not a directory, it should be a file, so attempt to get its contents
		if (substr ($location, -1) != '/') {
			return false;
		}
		
		# Check for the presence of a title file in the live site
		$file = $this->liveSiteRoot . $location . $this->pureContentTitleFile;
		if (file_exists ($file)) {
			if (is_readable ($file)) {
				return @file_get_contents ($file);
			}
		}
		
		# Check for the presence of a title file in the live site
		$file = $this->filestoreRoot . $location . $this->pureContentTitleFile;
		if (file_exists ($file)) {
			if (is_readable ($file)) {
				return @file_get_contents ($file);
			}
		}
		
		# Otherwise return false (this should never happen)
		return false;
	}
	
	
	# Function to make a timestamp
	function makeTimestamp ()
	{
		# Return the timestamp
		return date ('Ymd-His');
	}
	
	
	# Function to convert a timestamp to a string usable by strtotime
	function convertTimestamp ($timestamp, $includeTime = true)
	{
		# Convert the timestamp
		$timestamp = preg_replace ('/-(\d{2})(\d{2})(\d{2})$/', ' $1:$2:$3', $timestamp);
		
		# Determine the output string to use
		$format = 'l jS M Y' . ($includeTime ? ', g.ia' : '');	// Previously: ($includeTime ? 'g.ia \o\n ' : '') . 'jS M Y';
		
		# Convert the timestamp
		$string = date ($format, strtotime ($timestamp));
		
		# Return the string
		return $string;
	}
	
	
	# Function to remove a permission
	function permissionRevoke ()
	{
		# Get the users from the CSV file
		$permissions = $this->scopeSelectionList ();
		
		# If there are no permissions assigned, say so
		if (!$permissions) {
			echo "\n<p class=\"information\">There are no permissions assigned (other than universal permissions available to administrators). You may wish to <a href=\"{$this->page}?permissionGrant\">grant some permissions</a>.</p>";
			return;
		}
		
		# Create the form itself
		$form = new form (array (
			'developmentEnvironment' => $this->developmentEnvironment,
			'displayRestrictions' => false,
			'formName' => __FUNCTION__,
			'showFormCompleteText' => false,
			'submitTo' => "{$this->page}?" . __FUNCTION__,
			'submitButtonText' => 'Delete',
		));
		
		# Widgets
		$form->select (array ( 
		    'elementName'            => 'key', 
		    'valuesArray'            => $permissions, 
		    'title'                    => 'Permission to delete', 
			'elementDescription'	=> 'Permissions are listed in the form of <em>username (actual name): area</em>',
		    'minimumRequired'        => 1,
			'multiple' => false,
			'outputFormat'			=> array ('processing' => 'compiled'),
		));
		$form->input (array (
		    'elementName'            => 'confirmation', 
		    'title'                    => 'Confirm username',
			'elementDescription'	=> 'Please type in the username (as shown in the selection above, for confirmation',
		    'required'                => true, 
		));
		$form->textarea ($this->additionalMessageWidget);
		
		# Show the form and get any results or end here
		if (!$result = $form->processForm ()) {return;}
		
		# Split out the username and scope
		list ($username, $scope) = explode (':', $result['key'], 2);
		
		# Check the username matches
		if ($username != $result['confirmation']) {
			echo "\n<p class=\"failure\">The usernames you entered did not match. Please <a href=\"{$this->page}?" . __FUNCTION__ . '">go back</a> and try again.</p>';
			return false;
		}
		
		# Delete the entry
		if (!csv::deleteData ($this->permissionsDatabase, $result['key'], $this->databaseTimestampingMode)) {return false;}
		
		# Signal success
		echo "\n<p class=\"success\">The permission {$scope} for the user " . $this->convertUsername ($username) . ' was successfully deleted.</p>';
		
		# Log the change
		$this->logChange ("Revoked user {$username}'s permission to edit {$scope}");
		
		# Send an e-mail (but don't reload the database!)
		$this->sendMail ($username, "You no longer have permission to make changes to " . $this->convertPermission ($scope, $descriptions = true, $addLinks = false, $lowercaseStart = true) . ' .' . ($result['message'] ? "\n\n{$result['message']}" : ''));
		return true;
	}
	
	
	# Function to list and review submissions
	function review ($filename)
	{
		# Show the list if required
		$showList = (!$filename || ($filename && (!isSet ($this->submissions[$filename]))));
		if ($showList) {
			echo $this->listSubmissions ();
			return;
		}
		
		# Check that the live directory is writable before offering options
		if (!$this->directoryIsWritable ($this->liveSiteRoot, $this->currentDirectory)) {
			$this->reportErrors ('It is not currently possible to write files to the live site. The administrator needs to fix the permissions first.');
			return false;
		}
		
		# Create the form itself
		$form = new form (array (
			'developmentEnvironment' => $this->developmentEnvironment,
			'submitButtonText' => 'Take action',
			'showFormCompleteText' => false,
		));
		
		# Define the heading
		$fileDescription = ($this->livePage ? 'updated ' : 'new ') . $this->fileDescription ($filename);
		$fileLocation = $this->submissions[$filename]['directory'] . $this->submissions[$filename]['filename'];
		$form->heading ('', "<p class=\"information\">Please review the proposed <strong>" . $fileDescription . '</strong> below, submitted by ' . $this->convertUsername ($this->submissions[$filename]['username']) . ' on ' . $this->convertTimestamp ($this->submissions[$filename]['timestamp']) . ", and approve if it is acceptable. This is for the location " . (!file_exists ($this->liveSiteRoot . $fileLocation) ? "{$fileLocation} " : "<a title=\"Link opens in a new window\" target=\"_blank\" href=\"{$this->liveSiteUrl}{$fileLocation}\">{$fileLocation}</a>") . '.</p>');
		
		# Define the actions
		$actions = array (
			'approve'	=> 'Approve it (move to live site)',
			'reject'	=> 'Reject it outright (and delete the file)',
			'edit'		=> "I'll edit it further now [NB no message sent]",
			'message'	=> 'Only send a message to its creator',
		);
		$form->radiobuttons (array (
			'elementName'			=> 'action',
			'valuesArray'			=> $actions,
			'title'					=> 'Action',
			'required'		=> true,
		));
		$checkboxValue = 'Send message';
		$form->checkboxes (array (
		    'elementName'            => 'sendMessage', 
		    'valuesArray'            => array ($checkboxValue),
		    'title'                    => 'Inform user by e-mail?',
			'elementDescription' =>  'Note: adding any message below, or selecting the message option above, will also result in a message being sent. No message is sent if editing further.',
			'initialValues' => $checkboxValue,
		));
		$form->textarea ($this->additionalMessageWidget);
		
		# Get the file contents
		$fileOnServer = $this->filestoreRoot . $filename;
		
		# If the form is not processed, show the page
		if (!$result = $form->processForm ()) {
			chdir (str_replace ('\\', '/', dirname ($fileOnServer)));
			echo "\n<hr />";
			echo $this->showMaterial ($this->editableFileContents, 'information');
			return;
		}
		
		# Determine the file attributes
		$file = $this->submissions[$filename];
		
		# Construct the file location and date
		$fileLocation = $file['directory'] . $file['filename'];
		$fileTimestamp = $this->convertTimestamp ($file['timestamp']);
		
		# Flag to mail the user if explicitly requested or an additional message added
		$mailUser = (($result['action'] == 'message') || ($result['sendMessage'][$checkboxValue]) || ($result['message']));
		
		# Take action depending on the result
		switch ($result['action']) {
			case 'approve':
				$this->makeLive ($filename, $this->editableFileContents, $directly = false, $mailUser, $result['message']);
				break;
				
			case 'reject':
				
				# Delete the file
				if (!@unlink ($fileOnServer)) {
					$this->reportErrors ('There was a problem deleting the rejected file.', "The filename was {$fileOnServer} .");
					return false;
				}
				
				# Confirm success and relist the submissions
				echo "\n<p class=\"success\">The file $fileLocation was deleted successfully.";
				echo "\n<p><a href=\"/?review\"><strong>Please click here to continue</strong></a>, or continue moderating pages.</p>";
				echo "\n<hr />";
				echo $this->listSubmissions ($reload = true);
				
				# Log the change
				$this->logChange ("Submitted file $fileLocation deleted");
				
				# Mail the user if required
				if ($mailUser) {
					$compiledMessage = "The file you submitted, $fileLocation , on $fileTimestamp has been rejected and has thus deleted.";
					if ($result['message']) {$compiledMessage .= "\n\n{$result['message']}";}
					$this->sendMail ($file['username'], $compiledMessage);
				}
				
				break;
				
			case 'edit':
				# Redirect the user to the new page; take no other action. The previous version will need to be deleted manually by the administrator
				application::sendHeader (302, "{$this->editSiteUrl}{$fileLocation}?edit={$filename}");
				echo "\n<p><a href=\"{$fileLocation}?edit={$filename}\">Click here to edit the file</a> (as your browser has not redirected you automatically).</p>";
				
				break;
				
			case 'message':
				# Ensure a message has been given
				if (!$result['message']) {
					echo "<p class=\"failure\">You didn't enter a message! Please <a href=\"{$this->page}?" . __FUNCTION__ . "={$filename}\">go back</a> and try again.</p>";
					return;
				}
				
				# Send the message
				$compiledMessage = "Regarding the file you submitted, $fileLocation , on $fileTimestamp:\n\n{$result['message']}";
				$this->sendMail ($this->submissions[$filename]['username'], $compiledMessage);
				break;
		}
	}
	
	
	# Function to approve a file (i.e. make live)
	function makeLive ($submittedFile, $contents, $directly = false, $mailUser = false, $message = false)
	{
		# Construct the file location
		$newFileLiveLocation = ($directly ? $submittedFile : $this->submissions[$submittedFile]['directory'] . $this->submissions[$submittedFile]['filename']);
		$newFileLiveLocationFromRoot = $this->liveSiteRoot . $newFileLiveLocation;
		
		# Backup replaced live files if necessary
		if ($this->backupReplacedLiveFiles) {
			if (file_exists ($newFileLiveLocationFromRoot)) {
				$backupLocation = $newFileLiveLocation . '.' . date ('Ymd-His');
				if (!@copy ($newFileLiveLocationFromRoot, $this->liveSiteRoot . $backupLocation)) {
					$this->reportErrors ('The new file was not approved, as there was a problem backing up the existing file on the live site of the same name.', "This backup would have been at $backupLocation on the live site.");
					return false;
				}
				$this->logChange ("Backed up existing file on the live site $newFileLiveLocation as $backupLocation");
			}
		}
		
		# Install the new file on the live site
		if (!$installNewFileResult = application::createFileFromFullPath ($newFileLiveLocationFromRoot, $contents, $addStamp = false)) {
			$this->reportErrors ('There was a problem installing the approved file on the live site.', "This new file would have been at $newFileLiveLocation on the live site.");
		}
		$this->logChange (($directly ? 'New page directly' : "Submitted file $submittedFile approved and") . " saved to $newFileLiveLocation on live site");
		echo "<p class=\"success\">The file has been approved and is now online, at: <a title=\"Link opens in a new window\" target=\"_blank\" href=\"{$this->liveSiteUrl}{$newFileLiveLocation}\">{$this->liveSiteUrl}{$newFileLiveLocation}</a>.</p>";
		
		# Mail the user if required
		if ($mailUser) {
			$fileTimestamp = $this->convertTimestamp ($this->submissions[$submittedFile]['timestamp']);
			$compiledMessage = "The file you submitted, {$newFileLiveLocation} , on $fileTimestamp has been approved and is now online, at:\n\n{$this->liveSiteUrl}{$newFileLiveLocation}";
			if ($message) {$compiledMessage .= "\n\n{$message}";}
			$this->sendMail ($this->submissions[$submittedFile]['username'], $compiledMessage);
		}
		
		# Delete the staging file and log the change
		if (!$directly) {
			if (!@unlink ($this->filestoreRoot . $submittedFile)) {
				$this->reportErrors ('There was a problem deleting the originally submitted staging file.', "The filename was {$fileOnServer} .");
				return false;
			}
			$this->logChange ("Originally submitted (but now live) file $submittedFile deleted from filestore.");
		}
		
		# Return the cached result
		return ($installNewFileResult);
	}
	
	
	# Function to check whether an area is writable; provides facilities additional to is_writable
	function directoryIsWritable ($root, $location = '/')
	{
		# If there is a trailing slash, remove it
		if (substr ($location, -1) == '/') {$location = substr ($location, 0, -1);}
		
		# Split the directories up
		$directories = explode ('/', $location);
		
		# Loop through the directories while a list exist
		while (count ($directories)) {
			
			# Re-compile the location
			$directory = $root . implode ('/', $directories);
			
			# If the directory exists, test for its writability
			if (is_dir ($directory)) {
				if (is_writable ($directory)) {
					return true;
				}
			}
			
			# Remove the last directory in the list
			array_pop ($directories);
		}
		
		# Otherwise return false
		return false;
	}
	
	
	
	# Wrapper function to send the administrator an e-mail listing errors
	function reportErrors ($errors, $privateInfo = false)
	{
		# Ensure the errors are an array
		$errors = application::ensureArray ($errors);
		
		# Show the errors
		foreach ($errors as $error) {
			echo "\n<p class=\"failure\">$error</p>";
		}
		
		# Do not attempt to mail the administrator if no administrator address is available (which could be why an error is being thrown)
		if (!$this->serverAdministrator) {return false;}
		
		# Construct the message
		$introduction = 'The following ' . (count ($errors) == 1 ? 'problem has' : 'problems have') . ' been encountered:';
		$message = "\nDear webserver administrator,\n\n$introduction\n\n" . '- ' . implode ("\n\n- ", $errors);
		
		# If there is provide information, add this
		if ($privateInfo) {$message .= "\n\nAdditional diagnostic information:\n" . $privateInfo;}
		
		# Send the mail
		if ($this->sendMail ($this->serverAdministrator, $message, $showMessage = false)) {
			echo '<p class="information">The server administrator has been informed about ' . (count ($errors) == 1 ? 'this error' : 'these errors') . '.</p>';
		}
	}
	
	
	# Wrapper function to send e-mail
	function sendMail ($users, $message, $showMessage = true)
	{
		# If the user is actually an e-mail address, assign this directly; otherwise obtain attributes
		if ($users == $this->serverAdministrator) {
			$to[] = $this->serverAdministrator;
			$from = 'From: ' . $this->serverAdministrator;
		} else {
			
			# Loop through each user supplied
			$users = application::ensureArray ($users);
			
			# Get the user's/users' e-mail address and define the From header also
			foreach ($users as $user) {
				$to[] = $this->formatEmailAddress ($user);
				$name[] = $this->users[$user]['Forename'];
			}
			$from = 'From: ' . $this->formatEmailAddress ($this->user);
			
			# Add the user's name to the message, the signature, and login details
			$message  = "\nDear " . implode (', ', $name) . ",\n\n" . $message;
			$message .= "\n\n\n" . $this->messageSignatureGreeting . "\n" . $this->convertUsername ($this->user);
			$message .= "\n\n\n--\nAuthorised users can log into the pureContentEditor system at {$this->editSiteUrl}/ , using their Raven username and password.";
		}
		
		#!# At this point, perform check that the to(s) and from exist before trying to send it!
		
		# Compile the recipients
		$recipientList = implode (', ', $to);
		
		# Send the mail; ensure the editSiteUrl is set (it may not be if this function is being thrown by reportErrors ()
		$subject = 'Website editing facility' . ($this->editSiteUrl ? ' on ' . $this->editSiteUrl . '/' : '');
		if (!mail ($recipientList, $subject, wordwrap ($message), $from)) {
			echo "\n<p class=\"failure\">There was a problem sending an e-mail to the user.</p>";
			return false;
		}
		
		# Print the message if necessary
		if ($showMessage) {
			echo "\n<p class=\"success\">The following message has been sent:</p>";
			echo "\n<blockquote><pre>";
			echo "\n" . htmlentities ($from);
			echo "\n<strong>" . wordwrap ('To: ' . htmlentities ($recipientList)) . '</strong>';
			echo "\n" . wordwrap ('Subject: ' . htmlentities ($subject)) . '</strong>';
			echo "\n\n" . wordwrap (htmlentities ($message));
			echo "\n</pre></blockquote>";
		}
		
		# Signal success
		return true;
	}
	
	
	# Function to return a formatted e-mail string usable in mail (), given the username
	function formatEmailAddress ($user)
	{
		# Ensure the user exists
		if (!isSet ($this->users[$user])) {return false;}
		
		# Get the address
		$attributes = $this->users[$user];
		
		# Construct the string
		$string = "\"{$attributes['Forename']} {$attributes['Surname']}\" <{$attributes['E-mail']}>";
		
		# Return the string
		return $string;
	}
	
	
	# Function to get a description of the file
	function fileDescription ($filename)
	{
		# Get the file metadata
		$fileData = $this->submissions[$filename];
		
		# Section title file
		if ($fileData['filename'] == $this->pureContentTitleFile) {return "section title for the directory <a target=\"_blank\" href=\"{$fileData['directory']}\">{$fileData['directory']}</a>";}
		
		# Submenu file
		if ($fileData['filename'] == $this->pureContentMenuFile) {return 'contents of the submenu list';}
		
		# HTML page
		if ($fileData['extension'] == 'html') {return 'page';}
		
		# Else return a generic reference
		return 'submission';
	}
	
	# Function to list the awaiting submissions
	function listSubmissions ($reload = false)
	{
		# Reload the list, excluding template files
		$this->submissions = $this->submissions ($excludeTemplateFiles = true);
		
		# If there are no files awaiting review, say so and finish
		if (!$this->submissions) {
			echo "\n<p class=\"information\">There are no pages awaiting review at present.</p>";
			return;
		}
		
		# Start a table of data
		$html  = "\n<p>The following is a list of pages awaiting review:</p>";
		$html .= "\n" . '<table class="lines">';
		$html .= "\n\t" . '<tr>';
		$html .= "\n\t\t" . '<th>File</th>';
		$html .= "\n\t\t" . '<th>Submitted by</th>';
		$html .= "\n\t\t" . '<th>Time/date</th>';
		$html .= "\n\t" . '</tr>';
		
		# Loop through each file to create the table (these will automatically be ordered by filename then datestamp)
		foreach ($this->submissions as $file => $attributes) {
			
			# Clean the location
			$location = $attributes['directory'] . $attributes['filename'];
			
			# Create a table row
			$html .= "\n\t" . '<tr>';
			$html .= "\n\t\t" . "<td><a" . ($this->reviewPagesOpenNewWindow ? ' target="blank"': '') . " href=\"$location?review=$file\">$location</a></td>";
			$html .= "\n\t\t" . '<td>' . (($this->user == $attributes['username']) ? 'Myself' : $this->convertUsername ($attributes['username'])) . '</td>';
			$html .= "\n\t\t" . '<td>' . $this->convertTimestamp ($attributes['timestamp']) . '</td>';
			$html .= "\n\t" . '</tr>';
		}
		$html .= "\n" . '</table>';
		
		# Return the list
		return $html;
	}
	
	
	# Function to get a human-readable username
	function convertUsername ($user, $withUserId = true)
	{
		# Return the formatted string
		return $this->users[$user]['Forename'] . ' ' . $this->users[$user]['Surname'] . ($withUserId ? " ($user)" : '');
	}
	
	
	# Function to get all submissions
	function submissions ($excludeTemplateFiles = false)
	{
		# Determine whether to exclude files the size of the template
		$excludeFilesOfSize = ($excludeTemplateFiles ? strlen ($this->newPageTemplate) : false);
		
		# Get the file listing, excluding files the size of the template (in theory this may catch others, but in practice this is good enough - adding an md5() check would require opening all files and would reduce performance
		$files = directories::flattenedFileListing ($this->filestoreRoot, array (), $includeRoot = false, $excludeFilesOfSize);
		
		# Filter and organise the file listing
		$files = $this->submissionsFiltered ($files);
		
		# Return the list
		return $files;
	}
	
	
	# Function to filter and organise the file listing
	function submissionsFiltered ($files, $extensions = array ('html', 'txt', ))
	{
		# Loop through each file and build up a list of validated files
		$validatedFiles = array ();
		foreach ($files as $file) {
			
			# Create a fresh array to hold information about the file
			$attributes = array ();
			
			# Assign the directory, slash-terminated
			$attributes['directory'] = str_replace ('\\', '/', dirname ($file)) . '/';
			$attributes['directory'] = ((substr ($attributes['directory'], -2) == '//') ? substr ($attributes['directory'], 0, -1) : $attributes['directory']);
			
			# Get the filename and ensure it has three extensions (i.e. at least three dots)
			$filename = basename ($file);
			$fileinfo = explode ('.', $filename);
			if (count ($fileinfo) < 3) {continue;}
			
			# Get the username, timestamp and file extensions
			$attributes['username'] = array_pop ($fileinfo);
			$attributes['timestamp'] = array_pop ($fileinfo);
			$attributes['extension'] = array_pop ($fileinfo);
			
			# If a list of file extensions is supplied, and the file extension is not allowed, then discard this item and continue
			if ($extensions) {
				if (!in_array ($attributes['extension'], $extensions)) {continue;}
			}
			
			# Get the intended filename by resplicing the name back together
			$attributes['filename'] = implode ('.', $fileinfo) . '.' . $attributes['extension'];
			
			# Assign the information to the list of validated files
			$validatedFiles[$file] = $attributes;
		}
		
		# Return the validated files
		return $validatedFiles;
	}
	
	
	# Function to clean up the directory structure by removing empty directories
	function cleanUp ()
	{
		# Delete empty directories across the tree
		if ($problemsFound = directories::deleteEmptyDirectories ($this->filestoreRoot)) {
			$this->reportErrors ('Problems were encountered when attempting to delete empty folders in the filestore.', "The list of directories which did not delete is:\n" . implode ("\n", $problemsFound));
		}
	}
	
	
	# Function to provide a message form
	function message ()
	{
		# Get the administrators
		$users = array ();
		foreach ($this->users as $user => $attributes) {
			
			# Ensure that the current user cannot send a mesage to themselves
			if ($this->user == $user) {continue;}
			
			# If the user is not an administrator, do not list non-administrators
			if (!$this->userIsAdministrator && !$attributes['Administrator']) {continue;}
			
			# Add the user to the list
			$users[$user] = $this->convertUsername ($user);
		}
		
		# Finish if there are no users to send messages to
		if (!$users) {
			echo "\n<p class=\"information\">There are no users to whom messages can be sent.</p>";
			return;
		}
		
		# Create the form itself
		$form = new form (array (
			'developmentEnvironment' => $this->developmentEnvironment,
			'submitButtonText' => 'Send message',
			'displayDescriptions' => false,
			'showFormCompleteText' => false,
		));
		
		# Form widgets
		$form->select (array (
			'elementName'			=> 'username',
			'valuesArray'			=> $users,
			'title'					=> 'Send message to',
			'minimumRequired'		=> 1,
			'outputFormat'			=> array ('processing' => 'compiled'),
		));
		$form->textarea (array (
			'elementName'			=> 'message',
			'title'					=> 'Message',
			'required'				=> true,
			'columns'				=> 40,
		));
		
		# Set the processing options
		if (!$result = $form->processForm ()) {return false;}
		
		# Send the message
		$this->sendMail ($result['username'], $result['message']);
	}
}

#!# Add more info for all reportError calls so that they location info is always included to enable debugging
#!# Make the reviewing screen more user-friendly
#!# Larger textarea replacement size
#!# When doing include (), do a check first for the type of file; if a text file, just do a file_get_contents surround with <pre />
#!# Add an archiveStoreRoot for .old files
#!# Prevent creation of a permission when a more wide-ranging one exists


### Potential future development suggestions:
#R# Consider moving chdir into showMaterial ();
#R# Fix known but difficult bug in review (): 'reject' where the tasks list links all break because there is no longer a page there
#R# Implement a better algorithm for typeOfFile ()
#R# Implement the notion of a currently active permission which is definitive and which can be looked up against
# Specialised gui for menu and title files (rather than using 'list pages' or entering the URL directly)
# Automatic deletion of permissions when folders don't exist, if a setting is turned on for this (NB needs to distinguish between not present and no permission - may not be possible)
# More extensive menu editing system for the switch in edit ()
# Provide a validation system (perhaps using Tidy if it is not already)?
# Add a link checking mechanism
# Extension to deal with deleting/moving files or even whole folders? - would create major difficulties with integration with redirects etc, however
# Tighten up matching of ' src=' (currently will match that string outside an img tag)
# Allow browsing of empty folders - should suggest creating a file
# Move as many changes as possible made within /_fckeditor into the PHP constructor (as passed through ultimateForm.php)
# Remove preprocessContents () once FCKeditor.BaseHref works correctly
# Add use of application::getTitleFromFileContents in convertPermission () to get the contents for files
# Find some way to enable browsing of /foo/bar/[no index.html] where that is a new directory that does not exist on the live site - maybe a mod_rewrite change
# Renaming on making live
# More control over naming - moving regexp into the settings but disallow _ at the start

?>