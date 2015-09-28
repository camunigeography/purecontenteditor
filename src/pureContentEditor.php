<?php

/**
 * A class to create an editing facility on top of a pureContent-enabled site
 * 
 * @package pureContentEditor
 * @license	http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author	{@link http://www.geog.cam.ac.uk/contacts/webmaster.html Martin Lucas-Smith}, University of Cambridge 2004-14
 * @version See $version below
 * 
 * REQUIREMENTS:
 * - PHP should ideally have the Tidy extension compiled/loaded
 * - Requires libraries application.php, csv.php, directories.php, pureContent.php and ultimateForm.php, all available from http://download.geog.cam.ac.uk/projects/
 * - Uses the CKEditor (open source) and CKFinder (requires license purchase) DHTML components - http://ckeditor.com/ - to provide the richtext field
 * - Assumes that the server will supply a username - e.g. using AuthType or the University of Cambridge's Raven service
 * - Requires mod_rewrite enabled in Apache
 */



# Bugs / enhancements needed:

#!# Add more info for all reportError calls so that they location info is always included to enable debugging
#!# When doing include (), do a check first for the type of file; if a text file, just do a file_get_contents surround with <pre />
#!# Prevent creation of a permission when a more wide-ranging one exists, e.g. /foo/ being created when /foo/* exists or /* exists, rather than using the ksort in permissions()
#!# Delete all permissions when promoting to an administrator
#!# Option not to mail yourself when you approve your own page and you are the only administrator
#!# Audit the use of relative links in the richtext component
#!# Checking writability needs to be done on the proposed file, NOT at top level
#!# In edit mode, need to include pureContentHeadermenuFile/pureContentFootermenuFile in same way as browse mode



### Potential future development suggestions:
#R# Consider moving chdir into showMaterial ();
#R# Implement a better algorithm for typeOfFile ()
#R# Implement the notion of a currently active permission which is definitive and which can be looked up against
# Groups facility
# Automatic deletion of permissions when folders don't exist, if a setting is turned on for this (NB needs to distinguish between not present and no permission - may not be possible)
# More extensive menu editing system for the switch in edit ()
# Provide a validation system (perhaps using Tidy if it is not already)? - See: http://thraxil.org/users/anders/posts/2005/09/20/Validation-meet-Unit-Testing-Unit-Testing-meet-Validation/
# Add a link checking mechanism
# Extension to deal with deleting/moving files or even whole folders? - would create major difficulties with integration with redirects etc, however
# Tighten up matching of ' src=' (currently will match that string outside an img tag)
# Allow browsing of empty folders - should suggest creating a file
# Add use of application::getTitleFromFileContents in convertPermission () to get the contents for files
# Find some way to enable browsing of /foo/bar/[no index.html] where that is a new directory that does not exist on the live site - maybe a mod_rewrite change
# More control over naming - moving regexp into the settings but disallow _ at the start
# Ability to add a permission directly when adding a user rather than using two stages (and hence two e-mails)
# Moderation should cc: other administrators (not yourself though) when a page is approved
# Make /page.html rights the default when on a section page rather than an index page
# Enable explicit creation of .title.txt files
# Sort by ... for reviewing
# Diffing function - apparently wikimedia includes a good PHP class for this - see http://cvs.sourceforge.net/viewcvs.py/wikipedia/phase3/includes/  - difference engine
# Cookie, /login and own passwords ability; avoids :8080 links, etc; see also flags such as cookie/env at http://httpd.apache.org/docs/2.0/mod/mod_rewrite.html#rewriterule
# Direct update rights
# Link checker
# Option to ban top-level _directory_ creation as well as files
# Force .menu.html links to be absolute
# 'Mail all users' function (complete with "are you sure you want to?" confirmation)
# [New] symbol to mark when a page is new rather than updated
# Lookup-enabling interface for auto permissions
# Have a single 'master' port so that links are correct when running off two ports at once (or sort out /login ...)
# Change all file writes so that writability check is done in setup, removing the need for error reporting
#  sometimes not being escaped properly
# Start/end date should be in user not permission side
# Consider making administrator rights set as a select box rather than a difficultly-titled checkbox
# Need to disable the stub file being launched directly, using a check like ($_SERVER['DOCUMENT_ROOT'] . $_SERVER['REQUEST_URI'] == $_SERVER['SCRIPT_FILENAME']) but which takes into account query strings and port switching
# Find some way round the quirk that an area being limited by IP+Raven says that a username isn't being supplied, unless the user has FIRST logged in elsewhere on the same domain and 'AAForceInteract On' has been added to apache
# Find a less destructive way of setting the stylesheet layout for the actions box
# Set a way of allowing editor/filemanager/connectors/php/config.php  to change $Config['Enabled'] depending on the value of $_SERVER['REMOTE_USER']




# Create a class which adds editing functions to the pureContent framework
class pureContentEditor
{
	# Specify available arguments as defaults or as NULL (to represent a required argument)
	private $parameterDefaults = array (
		'editHostScheme' => 'http',				// Scheme of the editing site (i.e. http or https)
		'editHostName' => NULL,					// Hostname of the editing site
		'editHostPort' => 80,					// Port number of the editing site
		'liveSiteUrl' => NULL,					// Hostname of the live site
		'websiteName' => false,					// Name of the website, e.g. 'Department of XYZ' which proceeds 'website editing facility'
		'liveSiteRoot' => NULL,					// Directory where the main site files are located
		'filestoreRoot' => NULL,				// Directory where unapproved files are stored
		'authTypeName' => 'Raven',				// Name of the authorisation system in use (as in %authType username and password)
		'serverAdministrator' => NULL,			// E-mail of the server administrator
		'userDatabase' => '/.users.csv',		// User database
		'permissionsDatabase' => '/.permissions.csv',	// Permissions database
		'changelog' => '/.changelog.csv',		// Changelog
		'textareaEditorWidth' => '125',		// Textarea editor width (used only for HTML/PHP mode) as characters
		'textareaEditorHeight' => '30',		// Textarea editor height (used only for HTML/PHP mode) as characters
		'richtextEditorWidth' => '100%',		// Richtext editor width in pixels e.g. 400 or percent e.g. '80%'
		'richtextEditorHeight' => '400px',		// Richtext editor height in pixels e.g. 400 or percent e.g. '80%'
		'richtextEditorEditorAreaCSS' => array ('/sitetech/global.css', '/sitetech/generic.css'),	# CSS file to use in the editor area
		'richtextEditorBasePath' => '/_ckeditor/',	// Location of the DHTML editing component files
		'richtextEditorToolbarSet' => 'pureContent',	// Desired richtext editor Toolbar set
		'richtextEditorToolbarSetBasic' => 'BasicLonger',	// Name of the basic editor toolbar set used for submenu file editing
		'richtextEditorFileBrowser'	=> '/_ckfinder/',	// Path (must have trailing slash) of richtext file browser, or false to disable
		'directoryIndex' => 'index.html',		// Default directory index name
		'virtualPages'	=> false,		// Regexp location(s) where a page is claimed already to exist but there is no physical file
		'newPageTemplate' => "\n<h1>%title</h1>\n<p>Content starts here</p>",	// Default directory index file contents
		'newPageTemplateDefaultTitle' => "Title goes here",	// What %title normally becomes
		'newSubmenuTemplate' => "\n<ul>\n\t<li>Bullet-point list</li>\n\t<li>of menu items</li>\n</ul>",	// Default submenu file contents
		'newSidebarTemplate' => "\n<h2>Sidebar title goes here</h2>\n<p>Content starts here</p>",	// Default sidebar file contents
		'messageSignatureGreeting' => 'Best wishes,',	// Preset text for the e-mail signature to users
		'pureContentTitleFile' => '.title.txt',	// pureContent title file name
		'pureContentSubmenuFile' => '.menu.html',	// pureContent submenu file name
		'pureContentSidebarFile' => 'sidebar.html',	// pureContent sidebar file name
		'pureContentHeadermenuFile' => 'headermenu.html',	// pureContent headermenu file name
		'pureContentFootermenuFile' => 'footermenu.html',	// pureContent footermenu file name
		'pureContentMenuFile' => '/sitetech/menu.html',	// pureContent menu file name
		'enableHeaderImages' => false,			// Whether to enable the headers functionality
		'pureContentHeaderImageStore' => '/images/headers/',	// Section image header store
		'pureContentHeaderImageFilename' => 'header.jpg',	// Section image header filename
		'pureContentHeaderImageWidth' => false,		// Section image header width, or false for no checking
		'pureContentHeaderImageHeight' => false,	// Section image header height, or false for no checking
		'reviewPagesOpenNewWindow' => false,	// Whether pages for review should open in a new window or not
		'maximumFileAndFolderNameLength' => 25,	// Maximum number of characters for new files and folders
		'contentNegotiation' => false,			// Whether to switch on content-negotiation semantics when dealing with filenames
		'forcePagenameIndex' => false,			// Whether to force the basename of a page to be 'index'
		'minimumUsernameLength' => 3,			// Minimum allowable length of a username
		'databaseTimestampingMode' => '.old',	// Whether to backup old CSV databases with .old ('.old') or a timestamp (true) or not at all (false)
		'enableAliasingChecks' => true,			// Whether to enable checks for a server-aliased page if a page is not found
		'allowIndexAliasOverwriting' => true,	// Whether the system will allow the creation of a page that is being aliased
		'hideDirectoryNames' => array ('.AppleDouble', 'Network Trash Folder', 'TheVolumeSettingsFolder'), // Directory names to exclude from directory listings
		'wordwrapViewedSubmittedHtml' => false,	// Whether to wordwrap submitted HTML in the confirmation display (will not affect the file itself)
		'bannedLocations' => array (),			// List of banned locations where pages/folders cannot be created (even by an administrator) and which will not be listed
		'technicalFileLocations' => array ('/sitetech/*', '/robots.txt',  '/.htaccess', ),	// List of technical file locations, which are administrator-only
		'allowPageCreationAtRootLevel' => false,	// Whether to allow page creation at root level (e.g. example.com/page.html )
		'archiveReplacedLiveFiles' => true,		// Whether to backup files on the live site which have been replaced (either true [put in same location], false [no archiving] or a path
		'protectEmailAddresses' => true,	// Whether to obfuscate e-mail addresses
		'externalLinksTarget'	=> '_blank',	// The window target name which will be instanted for external links (as made within the editing system) or false
		'imageAlignmentByClass'	=> false,		// Replace align="foo" with class="foo" for images
		'imageConvertAbsolute'	=> false,		// Whether to pre-process the HTML to make images have absolute URLs
		'logout'	=> false,	// False if there is no logout available from the authentication agent or the location of the page
		'disableDateLimitation' => false,	// Whether to disable the date limitation functionality
		'allowNewLocation'		=> true,	// Whether to allow the adminisrator to approve the page at a new location
		'enablePhpCheck' => true,	// Whether to check for PHP
		'allImagesRequireAltText'	=> true,	// Whether all images require alt text to be supplied
		'blogs'			=> '/blogs/*',				// Blog root(s) - an array or single item; if ending with a *, indicates multiple underneath this root [only * currently supported]
		'newBlogEntryTemplate' => "\n\n\n<h1>%title</h1>\n\n<p>Content starts here</p>",	// Default blog posting file contents
		'newBlogIndexTemplate' => "<h1>%title</h1>\n\n<?php\nrequire_once ('pureContentBlogs.php');\necho pureContentBlogs::blogIndex ();\n?>",	// Default directory index file contents
		'newBlogTreeRootTemplate' => "<h1>Blogs</h1>\n\n<p>Welcome to the blogs section!</p>\n<p>The following blogs are available at present:</p>\n\n<?php\nrequire_once ('pureContentBlogs.php');\necho pureContentBlogs::blogList ();\n?>",
		'lookup'	=> array (),	// Array of areas which people have automatic editing rights rather than being stored by pureContentEditor, as array (username1 => array (Username,Forename,Surname,E-mail,Location and optionally Administrator (as value 1 or 0)), username2...)
		'bodyAttributes'	=> true, 	// Whether to apply body attributes to the editing area
		'charset'							=> 'UTF-8',		# Encoding used in entity conversions; www.joelonsoftware.com/articles/Unicode.html is worth a read
		'tipsUrl'				=> 'http://download.geog.cam.ac.uk/projects/purecontenteditor/tips.pdf',	// Location of tip sheet
		'helpNewWindow'				=> false,	// Whether the help page should open in a new window
		'makeLiveDefaultChecked' => true,	// Whether the 'make live by default' option should be checked by default
		'leaveLink'	=> false,		// Whether to add a link for 'leave editing mode'
		'nofixTag'	=> '<!-- nofix -->',	// Special marker which indicates that the HTML should not be cleaned (or false to disable)
		'allowCurlyQuotes' => false,	// Whether to allow curly quotes (e.g. from MS Word)
		'removeComments' => true,	// Whether the richtext editor should remove comments (NB disabling this will leave comment tags relating to WordHTML behind)
		'nameInEmail' => true,	// Whether e-mail should be formatted as Name <address@domain> or just address@domain
		'autocomplete' => false,	// Autocomplete data endpoint for adding new users
		'emailDomain'	=> false,	// E-mail domain, e.g. 'example.com', used for auto-populating of the e-mail address when using autocomplete
	);
	
	# Specify the minimum version of PHP required
	private $minimumPhpVersion = '5';
	
	# Version of this application
	private $version = '1.9.5';
	
	# HTML for the menu
	private $menuHtml = '';
	
	
	# Constructor
	public function __construct ($parameters = array ())
	{
		# Run the setup
		$html = $this->main ($parameters);
		
		# Enclose the entire application within a div to assist CSS styling
		$html = "\n" . '<div id="purecontenteditor">' . $html . "\n" . '</div>';
		
		# Show the HTML
		echo $html;
	}
	
	
	# Main function
	private function main ($parameters)
	{
		# Start the HTML
		$html = '';
		
		# Load required libraries
		require_once ('application.php');
		require_once ('csv.php');
		require_once ('directories.php');
		require_once ('pureContent.php');
		require_once ('ultimateForm.php');
		
		# State the expected fields from the user and permission databases
		$this->userDatabaseHeaders = array ('Username' , 'Forename' , 'Surname' , 'E-mail' , 'Administrator');
		$this->permissionsDatabaseFields = array ('Key', 'Username', 'Location', 'Self-approval', 'Startdate', 'Enddate', );
		
		# Assign the user
		$this->user = (isSet ($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : NULL);
		
		# Ensure the setup is OK
		if ($errorsHtml = $this->setup ($parameters)) {
			$html .= $errorsHtml;
			return $html;
		}
		
		# Get the current page and attributes from the query string
		if (!list ($this->page, $this->action, $this->attribute) = $this->parseQueryString ()) {
			$html .= application::showUserErrors ('The URL is invalid.');
			return $html;
		}
		
		# Redirect if required
		if ($this->action == 'returnto' && preg_match ('@^/@', $this->attribute)) {
			$redirectTo = $this->editSiteUrl . $this->attribute;
			application::sendHeader (302, $redirectTo);
			return $html;
		}
		
		# Get the users (which will force creation if there are none)
		if (!$this->users = $this->users ($errorsHtml)) {
			$html .= $errorsHtml;
			return $html;
		}
		
		# Get the current directory for this page
		$this->currentDirectory = $this->directoryOfPage ($this->page);
		
		# Get the administrators
		if (!$this->administrators = $this->administrators ($errorsHtml)) {
			$html .= $errorsHtml;
			return $html;
		}
		
		# Ensure the user is in the user list
		if (!$this->validUser ()) {
			$html .= application::showUserErrors ('You are not in the list of allowed users and so have no access to the editing facility.');
			return $html;
		}
		
		# Determine whether the user is an administrator
		$this->userIsAdministrator = $this->userIsAdministrator ();
		
		# Get the permissions for all users and then for the current user
		$this->permissions = $this->permissions ();
		$this->currentUserPermissions = $this->currentUserPermissions ();
		
		# Get the submissions
		$this->submissions = $this->submissions ();
		
		# Clean up the directory structure if necessary
		$html .= $this->cleanUp ();
		
		# Determine any staging page and any real page, and whether to use the staging page
		$this->livePage = $this->livePage ($this->page);
		$this->particularPage = $this->particularPage ();
		$this->stagingPage = $this->stagingPage ($this->page);
		
		# Determine if the page is a 404
		$this->pageIs404 = $this->pageIs404 ();
		
		# Determine if the page is being aliased
		$this->pageIsBeingAliased = $this->pageIsBeingAliased ();
		
		# Determine whether to use the staging page
		$this->pageToUse = $this->pageToUse ();
		
		# Determine the editable version to use
		$this->editableFile = $this->editableFile ($errorsHtml);
		if ($errorsHtml) {
			$html .= $errorsHtml;
			return $html;
		}
		
		# Get the contents of the latest version
		$this->editableFileContents = $this->editableFileContents ();
		
		# Determine the type of the file
		$this->typeOfFile = $this->typeOfFile ();
		
		# Determine whether the page contains PHP
		$this->pageContainsPhp = $this->pageContainsPhp ();
		
		# Determine whether to use blog mode
		list ($this->blogMode, $this->isBlogTreeRoot) = $this->blogMode ($this->currentDirectory);
		
		# Add to the list of banned locations the technical file locations, if the user is not an administrator
		if (!$this->userIsAdministrator && $this->technicalFileLocations) {$this->bannedLocations = array_merge ($this->bannedLocations, $this->technicalFileLocations);}
		
		# Determine whether the user can edit the current page
		$this->userCanEditCurrentPage = $this->userCanEditCurrentPage ();
		
		# Get the available actions
		$this->actions = $this->actions ();
		
		# Generate the menu (but do not show it yet, as it could get regenerated)
		$this->menuHtml = $this->generateMenu ();
		
		# Check that the action is allowed; 'live' and 'logout' are special cases as they are not real functions as such
		if (!array_key_exists ($this->action, $this->actions) || $this->action == 'live' || ($this->action == 'logout' && $this->logout)) {
			$html .= $this->menuHtml;
			$html .= "\n" . '<p class="failure">You appear to have requested a non-existent/unavailable function which is not available or to which you do not have access. Please use one of the links in the menu to continue.</p>';
			return $html;
		}
		
		# If the function is administrative but the user is not an administrator, end
		if ($this->actions[$this->action]['administratorsOnly'] && !$this->userIsAdministrator) {
			$html .= $this->menuHtml;
			$html .= "\n" . '<p class="failure">You are not an administrator, so cannot perform the requested operation.</p>';
			return $html;
		}
		
		# Take action, i.e. generate the page
		$pageHtml = $this->{$this->action} ($this->attribute);
		
		# Compile the HTML
		$html .= $this->menuHtml;
		$html .= $pageHtml;
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to setup the application
	private function setup ($parameters)
	{
		# Start a container for HTML errors
		$errorsHtml = '';
		
		# Check that all required arguments have been supplied, import supplied arguments and assign defaults
		foreach ($this->parameterDefaults as $parameter => $defaultValue) {
			if ((is_null ($defaultValue)) && (!isSet ($parameters[$parameter]))) {
				$setupErrors[] = "No '{$parameter}' has been supplied in the settings. This must be fixed by the administrator before this facility will work.";
			}
			$this->{$parameter} = (isSet ($parameters[$parameter]) ? $parameters[$parameter] : $defaultValue);
		}
		
		# Ensure the version of PHP is supported
		if (version_compare (PHP_VERSION, $this->minimumPhpVersion, '<')) {
			$setupErrors[] = "This program can only be run on PHP version {$this->minimumPhpVersion} or later.";
		}
		
		# Ensure that file uploads are enabled
		if (!ini_get ('file_uploads')) {$setupErrors[] = 'File uploading is not enabled - please ensure this is enabled in the server configuration.';}
		
		# Construct the edit site and live site URL
		$this->editSiteUrl = "{$this->editHostScheme}://{$this->editHostName}" . ($this->editHostPort != 80 ? ":{$this->editHostPort}" : '');
		
		# Ensure the URL is correct, i.e. the editing facility is not being run through an unauthorised location
		$httpHost = $_SERVER['HTTP_HOST'];
		if (substr_count ($_SERVER['HTTP_HOST'], ':')) {
			$hostAndPort = explode (':', $_SERVER['HTTP_HOST']);
			$httpHost = $hostAndPort[0];
		}
		if (($_SERVER['_SERVER_PROTOCOL_TYPE'] != $this->editHostScheme) || ($this->editHostName != $httpHost) || ($_SERVER['SERVER_PORT'] != $this->editHostPort)) {$setupErrors[] = 'The editing facility must be run from the URL specified in the settings.';}
		
		# Check that the server is defining a remote user
		if (!$this->user) {
			
			# Attempt to get a login from the front page of the site - sometimes the requested area may simply be set to "Allow from XXX" on the public side, which takes priority
			if (($_SERVER['REQUEST_URI'] != '/') && (!preg_match ('@^/\?returnto=@', $_SERVER['REQUEST_URI']))) {
				$redirectTo = "{$this->editSiteUrl}/?returnto=" . urlencode ($_SERVER['REQUEST_URI']);
				application::sendHeader (302, $redirectTo);
				$errorsHtml .= $this->reportErrors ('Attempting to redirect to a <a href="' . htmlspecialchars ($redirectTo) . '">login page</a> in case the current location does not support a login context... [NB If this message is visible, then output_buffering is not enabled for the present URL, though that may not be possible to enable.]');
				return $errorsHtml;
			}
			
			# Otherwise, register an error
			$setupErrors[] = 'The server did not supply a username, so the editing facility is unavailable.';
		}
		
		# Ensure the filestoreRoot liveSiteRoot are not slash-terminated
		$this->filestoreRoot = ((substr ($this->filestoreRoot, -1) == '/') ? substr ($this->filestoreRoot, 0, -1) : $this->filestoreRoot);
		$this->liveSiteRoot = ((substr ($this->liveSiteRoot, -1) == '/') ? substr ($this->liveSiteRoot, 0, -1) : $this->liveSiteRoot);
		
		# If a archiving is required and a location is specified then assign the archive root
		$this->archiveRoot = false;
		if ($this->archiveReplacedLiveFiles && $this->archiveReplacedLiveFiles !== true) {
			$this->archiveRoot = ((substr ($this->archiveReplacedLiveFiles, -1) == '/') ? substr ($this->archiveReplacedLiveFiles, 0, -1) : $this->archiveReplacedLiveFiles);
		}
		
		# Define the instantiating stub file (e.g. /sitetech/purecontenteditor/purecontenteditor.html) so this can be added to the banned locations
		$delimiter = '@';
		$this->stubFileLocation = preg_replace ($delimiter . '^' . addcslashes ($this->liveSiteRoot, $delimiter) . $delimiter, '', $_SERVER['SCRIPT_FILENAME']);
		
		# Ensure the bannedLocations and technical file locations are arrays
		$this->bannedLocations = application::ensureArray ($this->bannedLocations);
		if ($this->technicalFileLocations) {$this->technicalFileLocations = application::ensureArray ($this->technicalFileLocations);}
		
		# Confirm a list of banned locations (which also affects the administrator), including the stub file
		$this->bannedLocations[] = $this->stubFileLocation;
		
		# Ensure the filestore exists and is writable before continuing, if the location has been supplied
		if ($this->filestoreRoot) {
			if (!is_dir ($this->filestoreRoot)) {
				if (!@mkdir ($this->filestoreRoot, 0775, $recursive = true)) {
					$errorsHtml .= $this->reportErrors ('There was a problem creating the main filestore directory.', "The filestoreRoot, which cannot be created, specified in the settings, is: {$this->filestoreRoot}/");
					return $errorsHtml;
				}
			}
			if (!application::directoryIsWritable ($this->filestoreRoot)) {
				$setupErrors[] = 'It is not currently possible to write files to the filestore. The administrator needs to ensure the directory exists and fix the permissions first.';
			}
		}
		
		# Ensure the archive exists and is writable before continuing, if the location has been supplied
		if ($this->archiveRoot) {
			if (!is_dir ($this->archiveRoot)) {
				if (!@mkdir ($this->archiveRoot, 0775, $recursive = true)) {
					$errorsHtml .= $this->reportErrors ('There was a problem creating the main archive directory.', "The archiveRoot, which cannot be created, specified in the settings, is: {$this->archiveRoot}/");
					return $errorsHtml;
				}
			}
			if (!application::directoryIsWritable ($this->archiveRoot)) {
				$setupErrors[] = 'It is not currently possible to archive files to the archive. The administrator needs to ensure the directory exists and fix the permissions first.';
			}
		}
		
		# Ensure the header image store exists and is writable before continuing, if the location has been supplied
		if ($this->enableHeaderImages && $this->pureContentHeaderImageStore) {
			if (!is_dir ($this->liveSiteRoot . $this->pureContentHeaderImageStore)) {
				if (!@mkdir ($this->liveSiteRoot . $this->pureContentHeaderImageStore, 0775, $recursive = true)) {
					$errorsHtml .= $this->reportErrors ('There was a problem creating the main header image directory.', "The pureContentHeaderImageStore, which cannot be created, specified in the settings, is: {$this->pureContentHeaderImageStore}/");
					return $errorsHtml;
				}
			}
			if (!application::directoryIsWritable ($this->liveSiteRoot . $this->pureContentHeaderImageStore)) {
				$setupErrors[] = 'It is not currently possible to archive files to the header image directory. The administrator needs to ensure the directory exists and fix the permissions first.';
			}
		}
		
		# Define an array for an additional message (used in several places);
		$this->additionalMessageWidget = array (
			'name'			=> 'message',
			'title'			=> 'Any additional message',
			'required'		=> false,
			'cols'			=> 40,
			'rows'			=> 3,
		);
		
		# Define the user and permissions database locations
		$this->userDatabase = $this->filestoreRoot . $this->userDatabase;
		$this->permissionsDatabase = $this->filestoreRoot . $this->permissionsDatabase;
		
		# Ensure any lookup is an array
		$this->lookup = application::ensureArray ($this->lookup);
		
		# Ensure the permissions database exists, by creating it if it doesn't exist or is an empty file
		if (!file_exists ($this->permissionsDatabase) || !filesize ($this->permissionsDatabase)) {
			if (!csv::createNew ($this->permissionsDatabase, $this->permissionsDatabaseFields)) {
				$setupErrors[] = 'There was a problem creating the permissions database.';
			}
		}
		
		# Ensure the user database exists, by creating it if it doesn't exist or is an empty file
		if (!file_exists ($this->userDatabase) || !filesize ($this->userDatabase)) {
			if (!csv::createNew ($this->userDatabase, $this->userDatabaseHeaders)) {
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
			$errorsHtml .= $this->reportErrors ($setupErrors);
			return $errorsHtml;
		}
		
		# Otherwise return success
		return $errorsHtml;
	}
	
	
	# Function to get the live page filename
	private function livePage ($page)
	{
		# Construct the filename
		$file = $this->liveSiteRoot . $page;
		
		# Return the page filename or false
		return $livePageFile = (file_exists ($file) ? $file : false);
	}
	
	
	# Function to get a specified page filename
	private function particularPage ()
	{
		# A particular page can be specified if the action is editing/browsing/reviewing and the page is specified as an attribute and exists
		if ($this->action != 'edit' && $this->action != 'browse' && $this->action != 'review') {return false;}
		if (!isSet ($this->submissions[$this->attribute])) {return false;}
		
		# Construct the filename
		return $particularPageFile = $this->attribute;
	}
	
	
	# Function to get the staging page filename
	private function stagingPage ($page)
	{
		# Assume there is no staging page
		$stagingPage = false;
		
		# Loop through the submissions assigning the last (i.e. latest) file as the chosen one if there are several
		foreach ($this->submissions as $file => $attributes) {
			if ($page == ($attributes['directory'] . $attributes['filename'])) {
				$stagingPage = $file;	// NB This exists, otherwise it would not be in the submissions list
			}
		}
		
		# Return the filename or false
		return $stagingPage;
	}
	
	
	# Function to determine if the page is not present
	private function pageIs404 ()
	{
		# If there is a staging page, the page is present
		if ($this->stagingPage) {return false;}
		
		# If the server has thrown a 404 and there is no staging page, replicate the 404
		if (isSet ($_SERVER['REDIRECT_STATUS'])) {
			if ($_SERVER['REDIRECT_STATUS'] == '404') {
				return true;
			}
		}
		
		# Return false
		return false;
	}
	
	
	# Function to determine if the page is being aliased (basically if the page is absent on the staging side, but there is a response from the live site, then aliasing is likely to be in use)
	private function pageIsBeingAliased ()
	{
		# Return false if aliasing checks are not permitted
		if (!$this->enableAliasingChecks) {return false;}
		
		# If there is a live page or a staging page then the page is not being aliased
		if ($this->livePage || $this->stagingPage) {return false;}
		
		# If the page is not a 404 but an HTTP request finds that there is a page response, the page is being aliased
//		if (!$this->pageIs404) {
			if (@file_get_contents ($this->liveSiteUrl . $this->page)) {
				return true;
			}
//		}
		
		# Otherwise return no errors
		return false;
	}
	
	
	# Function to check whether a current directory contains an index page
	private function directoryContainsIndex ()
	{
		# Return true if a virtual page exists
		if ($this->matchLocation ($this->virtualPages, $this->currentDirectory . $this->directoryIndex)) {return true;}
		
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
	private function pageToUse ()
	{
		# If the file is new, return false as there is no page to use
		if (!$this->particularPage && !$this->livePage && !$this->stagingPage) {return false;}
		
		# If a particular page has been defined, return that
		if ($this->particularPage) {return 'particular';}
		
		# Flag yes if there is no live page (NB pageNotPresent() will have been run by now)
		if (!$this->livePage) {return 'staging';}
		
		# Flag no if there is no staging page
		if (!$this->stagingPage) {return 'live';}
		
		# If, now that a staging page is confirmed present, the original is being requested, return false
		if ((($this->action == 'edit') || ($this->action == 'browse')) && ($this->attribute != 'live')) {return 'staging';}
		
		# Otherwise return false
		return 'live';
	}
	
	
	# Function to determine the latest version in use
	private function editableFile (&$errorsHtml = '')
	{
		# If the file is new, return true
		if (!$this->pageToUse) {return false;}
		
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
		
		# Check that the file is readable
		if (!is_readable ($editableFile)) {
			$errorsHtml .= $this->reportErrors ('There was a problem opening the file.', "The file name is {$editableFile} .");
			return false;
		}
		
		# Return the filename
		return $editableFile;
	}
	
	
	# Function to get the contents of the latest version in use
	private function editableFileContents ()
	{
		# If there is no file
		if (!$this->editableFile) {return false;}
		
		# Read the file contents, which we already know is readable
		$contents = file_get_contents ($this->editableFile);
		
		# Return the contents
		return $contents;
	}
	
	
	# Function to determine whether the editor should run in blog mode, returning the root of the blog if so, and whether the supplied location is the blog tree root
	private function blogMode ($location)
	{
		# Create a blog mode reminder
		$this->blogModeReminder = "\n<p class=\"warning\"><strong>Reminder</strong>: Blog entries should <strong>not</strong> be a replacement for material properly organised within the main site hierarchy.<br />Make sure material that should be within the main site is put there <strong>before</strong> creating the blog posting.</p>";
		
		# Assume that we are not in any blog tree root directory
		$isBlogTreeRoot = false;
		
		# Return false if no blog
		if (!$this->blogs) {return false;}
		
		# Ensure there is a list of blogs, even if only one
		$blogs = application::ensureArray ($this->blogs);
		
		# Loop through to check for a match
		foreach ($blogs as $blog) {
			
			# Chop off a final * if found
			if (substr ($blog, -1) == '*') {
				$blog = substr ($blog, 0, -1);
				
				# If the current directory exactly matches the root, assign it as the blog tree root
				if ($location == $blog) {
					$isBlogTreeRoot = true;
					return array ($blog, $isBlogTreeRoot);
				}
			}
			
			# Return true if the URL matches normally (e.g. the current URL is /blogs/path/to/some/posting/ which will match the blog root /blog/)
			$delimiter = '@';
			if (preg_match ($delimiter . '^' . addcslashes ($blog, $delimiter) . $delimiter, $location)) {
				return array ($blog, $isBlogTreeRoot);
			}
		}
		
		# Return false if not found
		return array (false, $isBlogTreeRoot);
	}
	
	
	# Function to pre-process the page contents
	private function preprocessContents ($content)
	{
		# Standard replacements
		$replacements = array (
			" src=\"{$this->editSiteUrl}/"	=> ' src="/',			// Ensure images are not prefixed with the current site's URL
		);
		
		# Force images to absolute if required
		if ($this->imageConvertAbsolute) {
			$replacements += array (
				' src="([^/|http://|https://])'				=> ' src="' . $this->currentDirectory . '\\1',
			);
		}
		
		# Replacement of legacy image class with a similarly-named align attribute (if imageAlignmentByClass is enabled, then this is then reversed afterwards - this is so that the DHTML editor picks up the alignment correctly
		$replacements += array (
			'<img([^>]*) class="(left|center|right)"([^>]*)>' => '<img$1 align="$2"$3>',
			'<img([^>]*) class="(centre)"([^>]*)>' => '<img$1 align="center"$3>',
		);
		
		# Perform the replacements
		$delimiter = '@';	// Must not be in the strings above
		foreach ($replacements as $find => $replace) {
			$content = preg_replace ($delimiter . $find . $delimiter, $replace, $content);
		}
		
		# Return the contents
		return $content;
	}
	
	
	# Function to parse the query string and return the page and attributes; this is basically the guts of dealing with all the hacked mod-rewrite rerouting stuff
	private function parseQueryString ()
	{
		# Check for location-independent URL handling
		if (preg_match ('/^(([^\?]+)\.[0-9]{8}-[0-9]{6}\.[^\?]+)(\?([a-zA-Z]+))?$/', $_SERVER['REQUEST_URI'], $matches)) {
			
			# Location-independent URL handling; this is where /foo/bar.html.YYYYMMDD-hhmmss.username?action is used to derive the page itself as /foo/bar.html rather than /foo/bar.html?action=/foo/bar.html.YYYYMMDD-hhmmss.username?action
			
			/* E.g. for 
			/foo/bar.html.20070822-155250.username?review
			the above regexp sets $matches as:
			Array
			(
			    [0] => /foo/bar.html.20070822-155250.username?review		// Full match
			    [1] => /foo/bar.html.20070822-155250.username				// Attribute, i.e. specific version of page
			    [2] => /foo/bar.html										// Page itself
			    [3] => ?review												// Unwanted; capture required because ()? needed for optional action in regexp
			    [4] => review												// Action
			)
			*/
			
			# Assign the matches
			$page = $matches[2];
			$action = ($matches[4] ? $matches[4] : 'browse');
			$attribute = $matches[1];
			
		# Otherwise use the standard, previous default behaviour
		} else {
			
			# Ensure the query string is in UTF-8 if required
			$queryString = $_SERVER['QUERY_STRING'];
			if ($this->charset == 'UTF-8') {
				$queryString = utf8_encode (urldecode ($_SERVER['QUERY_STRING']));
			}
			
			# Get the query
			$query = explode ('&', $queryString);
			
			# Assign the page
			$page = (!isSet ($_SERVER['REDIRECT_SCRIPT_URL']) ? $query[0] : $_SERVER['REDIRECT_SCRIPT_URL']);
			
			# If the user has requested a directory, ensure it internally ends with the directory index
			if (substr ($page, -1) == '/') {$page .= $this->directoryIndex;}
			
			// # Disallow loading of the instantiating stub file
			// if ($page == $this->stubFileLocation) {return false;}
			
			# Obtain the current action and set the initial attribute
			$action = 'browse';
			$attribute = false;
			if (isSet ($query[1])) {
				$split = explode ('=', $query[1]);
				$action = $split[0];
				$attribute = (isSet ($split[1]) ? $split[1] : '');
			}
		}
		
		# Return the query and attribute
		return array ($page, $action, $attribute);
	}
	
	
	# Function to get the directory for a supplied page
	private function directoryOfPage ($page)
	{
		# Normalise the page
		$page = str_replace ('\\', '/', $page);
		
		# Get the directory
		$directory = (substr ($page, -1) == '/' ? $page : str_replace ('\\', '/', dirname ($page)));
		
		# Slash-terminate if necessary
		if (substr ($directory, -1) != '/') {$directory .= '/';}
		
		# Return the result
		return $directory;
	}
	
	
	# Function to check if the current directory exists
	private function currentDirectoryExists ()
	{
		# If the current directory exists on the live or staging sides, return true
		if (is_dir ($this->liveSiteRoot . $this->currentDirectory)) {return true;}
		if (is_dir ($this->filestoreRoot . $this->currentDirectory)) {return true;}
		
		# There is no such directory, so return false
		return false;
	}
	
	
	# Function to get users
	private function users (&$html)
	{
		# Get the CSV (local) users, ensuring the result is an array so that array_merge doesn't crash
		if (!$csvUsers = csv::getData ($this->userDatabase)) {
			$csvUsers = array ();
		}
		
		# Get the lookup data
		$lookupUsers = array ();
		if ($this->lookup) {
			
			# Label the CSV users
			#!# Should show when lookup is !== false rather than when no lookup users found
			if ($csvUsers) {
				foreach ($csvUsers as $username => $attributes) {
					$csvUsers[$username]['Source'] = 'Local (CSV)';
				}
			}
			
			# Organise the lookup users
			$fields = array ('Forename', 'Surname', 'E-mail', 'Administrator');
			foreach ($this->lookup as $index => $attributes) {
				if (!isSet ($attributes['Username'])) {continue;}
				$username = $attributes['Username'];
				foreach ($fields as $field) {
					$lookupUsers[$username][$field] = (isSet ($attributes[$field]) ? trim ($attributes[$field]) : '');
				}
				$lookupUsers[$username]['Source'] = 'Lookup (database)';
			}
		}
		
		# Merge the users, with the CSV (local) users taking precedence
		$users = array_merge ($lookupUsers, $csvUsers);
		
		# End if there are no users and force addition
		if (!$users) {
			$html .= $this->userAdd ($firstRun = true);
			return false;
		}
		
		# Sort the users by username
		#!# Ideally replace with a function that sorts by Surname,Forename and maintains key association
		ksort ($users);
		
		# Return the users
		return $users;
	}
	
	
	# Function to get permissions
	private function permissions ()
	{
		# Get the data and return it
		$csvPermissions = csv::getData ($this->permissionsDatabase);
		
		# Ensure that all fields are present in the CSV data
		foreach ($csvPermissions as $username => $attributes) {
			foreach ($this->permissionsDatabaseFields as $field) {
				$csvPermissions[$username][$field] = (isSet ($attributes[$field]) ? trim ($attributes[$field]) : '');
			}
		}
		
		# Get the lookup data
		$lookupPermissions = array ();
		if ($this->lookup) {
			
			# Label the CSV permissions
			#!# Should show when lookup is !== false rather than when no lookup permissions found
			if ($csvPermissions) {
				foreach ($csvPermissions as $username => $attributes) {
					$csvPermissions[$username]['Source'] = 'Local (CSV)';
				}
			}
			
			# Organise the lookup permissions
			#!# Currently assumes one permission per user; change this in a later release by allowing an array of permissions per user
			foreach ($this->lookup as $username => $attributes) {
				$permission = array ();
				foreach ($this->permissionsDatabaseFields as $field) {
					$permission[$field] = (isSet ($attributes[$field]) ? trim ($attributes[$field]) : '');
				}
				$key = trim ($username) . ':' . trim ($permission['Location']);
				$lookupPermissions[$key] = $permission;
				$lookupPermissions[$key]['Source'] = 'Lookup (database)';
			}
		}
		
		# Merge the permissions, with the CSV (local) users taking precedence
		$permissions = array_merge ($lookupPermissions, $csvPermissions);
		
		# Sort the permissions, maintaining key to data correlations; this will ensure that overriding rights are listed (and will thus be matched) first (i.e. tree>directory>page)
		ksort ($permissions);
		
		# Return the permissions
		return $permissions;
	}
	
	
	# Function to get administrators
	private function administrators (&$errorsHtml = '') {
		
		# Determine the administrators
		$administrators = array ();
		foreach ($this->users as $user => $attributes) {
			if ($attributes['Administrator']) {
				$administrators[] = $user;
			}
		}
		
		# Throw an error if there are no administrators
		if (!$administrators) {
			$errorsHtml .= $this->reportErrors ('There are no administrators assigned. Somehow the user database has been edited incorrectly. The administrator should reset the user database or edit it directly to ensure there is an administrator.');
			return false;
		}
		
		# Return the administrators
		return $administrators;
	}
	
	
	# Function to determine whether the user is an administrator
	private function userIsAdministrator ($particularUser = false)
	{
		# Determine the user to check, defaulting to the administrator
		$userToCheck = ($particularUser ? $particularUser : $this->user);
		
		# Return the result from the array
		return (isSet ($this->users[$userToCheck]['Administrator']) ? $this->users[$userToCheck]['Administrator'] : false);
	}
	
	
	# Function to determine whether the user has editing rights
	private function userHasPageEditingRights ($page)
	{
		# Obtain the user's rights
		$rights = $this->determineRights ($page);
		
		# Determine this type of right
		$userHasPageEditingRights = ($rights);
		
		# Return the result
		return $userHasPageEditingRights;
	}
	
	
	# Function to determine if the user has page creation rights
	private function userHasPageCreationRights ($page, $ignoreRootCheck = false)
	{
		# Obtain the user's rights
		$rights = $this->determineRights ($page);
		
		# Determine the directory for this page
		$directory = $this->directoryOfPage ($page);
		
		# Determine whether page creation is being disallowed at this location due to root level disallowing
		$rootPageCreationRestrictionApplies = ($directory == '/' && !$this->allowPageCreationAtRootLevel && !$ignoreRootCheck);
		
		# Look up blog mode for this area
		list ($blogMode, $isBlogTreeRoot) = $this->blogMode ($directory);
		
		# Determine this type of right
		$userHasPageCreationRights = (($rights == 'tree' || $rights == 'directory') && !$rootPageCreationRestrictionApplies && !$isBlogTreeRoot);
		
		# Return the result
		return $userHasPageCreationRights;
	}
	
	
	# Function to determine if the user has folder creation rights
	private function userHasFolderCreationRights ($page)
	{
		# Obtain the user's rights
		$rights = $this->determineRights ($page);
		
		# Determine the directory for this page
		$directory = $this->directoryOfPage ($page);
		
		# Look up blog mode for this area
		list ($blogMode, $isBlogTreeRoot) = $this->blogMode ($directory);
		
		# Determine this type of right
		$userHasFolderCreationRights = (($rights == 'tree') && ((!$blogMode) || ($blogMode && $isBlogTreeRoot)));
		
		# Return the result
		return $userHasFolderCreationRights;
	}
	
	
	# Function to determine the user's rights overall
	private function determineRights ($page)
	{
		# Determine if the user can make files live directory (further changes below)
		$this->userCanMakeFilesLiveDirectly = ($this->userIsAdministrator ? true : false);
		
		# Return false if the page is banned
		if ($this->changesBannedHere ($page)) {return false;}
		
		# Return true if the user is an administrator
		if ($this->userIsAdministrator) {return true;}
		
		# Determine the locations in the current user's permissions for matching
		$locations = array ();
		foreach ($this->currentUserPermissions as $permission) {
			$locations[] = $this->permissions[$permission]['Location'];
		}
		
		# Get the user's rights in detail
		$rights = $this->matchLocation ($locations, $page, $determineLocationInUse = true);
		
		# Determine the exact permission in use
		$permission = ($this->locationInUse ? "{$this->user}:{$this->locationInUse}" : false);
		$this->userCanMakeFilesLiveDirectly = ($this->userIsAdministrator ? true : (($permission && isSet ($this->permissions[$permission])) ? $this->permissions[$permission]['Self-approval'] : false));
		
		# Return the user's rights
		return $rights;
	}
	
	
	# Function to determine whether the supplied page location is banned
	private function changesBannedHere ($location)
	{
		# Return the result directly
		return $this->matchLocation ($this->bannedLocations, $location);
	}
	
	
	# Function to perform a location match; returns either a string (equating to true) or false
	private function matchLocation ($locations, $locationToTest, $determineLocationInUse = false)
	{
		# Set the default for the location in use
		if ($determineLocationInUse) {$this->locationInUse = false;}
		
		# End if no locations
		if (!$locations) {return false;}
		
		# Convert the locations to an array if not already
		$locations = application::ensureArray ($locations);
		
		# Loop through each location (which are ordered such that overriding rights are listed (and will thus be matched) first (i.e. tree>directory>page)
		$delimiter = '@';
		foreach ($locations as $location) {
			
			# Check for an exact match
			if ($location == $locationToTest) {
				if ($determineLocationInUse) {$this->locationInUse = $location;}
				return 'page';	// i.e. true
			}
			
			# Check for pages in the same directory
			if (substr ($location, -1) == '/') {
				$page = preg_replace ($delimiter . '^' . addcslashes ($location, $delimiter) . $delimiter, '', $locationToTest);
				if (strpos ($page, '/') === false) {
					if ($determineLocationInUse) {$this->locationInUse = $location;}
					return 'directory';	// i.e. true
				}
			}
			
			# Check for pages below the test location
			if (substr ($location, -1) == '*') {
				if (preg_match ($delimiter . '^' . addcslashes ($location, $delimiter) . $delimiter, $locationToTest)) {
					if ($location != preg_replace ($delimiter . '^' . addcslashes ($location, $delimiter) . $delimiter, '', $locationToTest)) {
						if ($determineLocationInUse) {$this->locationInUse = $location;}
						return 'tree';	// i.e. true
					}
				}
			}
			
			# Check for exact regexp matches, which would be a on a per-page basis as the others have not caught it
			if (preg_match ($delimiter . addcslashes ($location, $delimiter) . $delimiter, $locationToTest)) {
				return 'page';	// i.e. true
			}
		}
		
		# Else return false
		return false;
	}
	
	
	# Function to determine whether the user can edit the current page
	private function userCanEditCurrentPage ()
	{
		# Set a flag for whether the page contains the string <?php ; return false if found; however, administrators can edit the page, but in non-WYSIWYG mode
		if ($this->pageContainsPhp && !$this->userIsAdministrator) {return false;}
		
		# If the page is being aliased, the source is therefore not available so cannot be edited
		if ($this->pageIsBeingAliased) {return false;}
		
		# Otherwise return whether the user has rights here
		return $this->userHasPageEditingRights ($this->page);
	}
	
	
	# Function to determine whether the page contains PHP instructions
	private function pageContainsPhp ()
	{
		# Return false if checking not required
		if (!$this->enablePhpCheck) {return false;}
		
		# If in memory, then return false
		if (!$this->editableFile) {return false;}
		
		# Check whether the page contains the string <?php
		return (substr_count ($this->editableFileContents, '<?php'));
	}
	
	
	# Function to get the type of file
	private function typeOfFile ()
	{
		# Get the filename
		$filename = basename ($this->page);
		
		# Assign a preg delimiter
		$delimiter = '@';
		
		# Title file, starts with the string contained in $this->pureContentTitleFile
		if (preg_match ($delimiter . '^' . addcslashes ($this->pureContentTitleFile, $delimiter) . $delimiter, $filename)) {return 'titleFile';}
		
		# Menu file, starts with the string contained in $this->pureContentSubmenuFile
		if (preg_match ($delimiter . '^' . addcslashes ($this->pureContentSubmenuFile, $delimiter) . $delimiter, $filename)) {return 'submenuFile';}
		
		# Menu file, starts with the string contained in $this->pureContentSidebarFile
		if (preg_match ($delimiter . '^' . addcslashes ($this->pureContentSidebarFile, $delimiter) . $delimiter, $filename)) {return 'sidebarFile';}
		
		# Text files
		if (preg_match ($delimiter . '\.txt((\.[0-9]{8}-[0-9]{6}\..+)?)$' . $delimiter, $filename)) {return 'txtFile';}
		
		# CSS files
		if (preg_match ($delimiter . '\.css((\.[0-9]{8}-[0-9]{6}\..+)?)$' . $delimiter, $filename)) {return 'cssFile';}
		
		# Javascript files
		if (preg_match ($delimiter . '\.js((\.[0-9]{8}-[0-9]{6}\..+)?)$' . $delimiter, $filename)) {return 'jsFile';}
		
		// If $this->editableFile === false then this will also be assumed to be a page below as none of the above will have caught
		
		# Default to a page
		return 'page';
	}
	
	
	# Function to get menu actions and their permissions
	private function actions ()
	{
		# Determine the location of certain special files
		$directoryComponents = explode ('/', trim ($this->currentDirectory));
		$submenuLocation = (count ($directoryComponents) >= 3 ? '/' . $directoryComponents[1] . '/' . $this->pureContentSubmenuFile : false);
		
		# Create an array of the actions
		$actions = array (
			'browse' => array (
				'title' => 'Browse site',
				'tooltip' => 'Browse the site as normal and find pages to edit',
				'administratorsOnly' => false,
				'grouping' => 'Main actions',
			),
			
			'edit' => array (
				'title' => (($this->blogMode && !$this->isBlogTreeRoot) ? 'Edit this blog posting' : '<strong>Edit this page</strong>'),
				'tooltip' => ($this->blogMode ? 'Edit the current blog posting' : 'Edit the current page'),
				'administratorsOnly' => false,
				'grouping' => 'Main actions',
				'check' => 'userCanEditCurrentPage',
				// 'locationInsensitive' => true, // Not defineable here
			),
			
			'live' => array (
				'title' => 'View live',
				'tooltip' => 'View the live equivalent of this page',
				'url' => $this->liveSiteUrl . $this->chopDirectoryIndex ($this->page) . '" target="_blank',
				'administratorsOnly' => false,
				'grouping' => 'Main actions',
				'check' => 'livePage',
			),
			
			'section' => array (
				'title' => ($this->blogMode ? 'Create new blog' : 'Create new section here'),
				'tooltip' => ($this->blogMode ? 'Create a new blog' : 'Create a new section (set of pages)'),
				'administratorsOnly' => false,
				'grouping' => 'Main actions',
				'check' => $this->userHasFolderCreationRights ($this->page),
			),
			
			'newPage' => array (
				'title' => ($this->blogMode ? 'Create new blog posting' : 'Create new page here'),
				'tooltip' => ($this->blogMode ? 'Create a new entry within this blog' : 'Create a new page within this existing section'),
				'administratorsOnly' => false,
				'grouping' => 'Main actions',
				'check' => $this->userHasPageCreationRights ($this->page),
			),
			
			'breadcrumb' => array (
				'title' => 'Breadcrumb',
				'tooltip' => 'Edit or create the breadcrumb trail item for this main section',
				'url' => $this->currentDirectory . $this->pureContentTitleFile . '?breadcrumb',
				'administratorsOnly' => false,
				'grouping' => 'Navigation',
				'check' => $this->userHasPageCreationRights ($this->page),
			),
			
			'submenu' => array (
				'title' => 'Submenu',
				'tooltip' => 'Edit or create the menu for this section',
				'url' => $submenuLocation . '?submenu',
				'administratorsOnly' => false,
				'grouping' => 'Navigation',
				'check' => ((bool) ($submenuLocation) && $this->userHasPageCreationRights ($submenuLocation)),
			),
			
			'sidebar' => array (
				'title' => 'Sidebar',
				'tooltip' => 'Edit or create a sidebar item visible on all pages in this section',
				'url' => $this->currentDirectory . $this->pureContentSidebarFile . '?sidebar',
				'administratorsOnly' => false,
				'grouping' => 'Navigation',
				'check' => $this->userHasPageCreationRights ($this->page, $ignoreRootCheck = true),
			),
			
			'headerimage' => array (
				'title' => 'Header image',
				'tooltip' => 'Change or create a header image visible on all pages in this section',
				'url' => $this->currentDirectory . '?headerimage',
				'administratorsOnly' => false,
				'grouping' => 'Navigation',
				'check' => $this->enableHeaderImages && $this->userHasPageCreationRights ($this->page, $ignoreRootCheck = true),
			),
			
			'myAreas' => array (
				'title' => 'My areas',
				'tooltip' => 'List the areas which I have access to make changes to',
				'administratorsOnly' => false,
				'grouping' => 'Additional',
			),
			
			'showCurrent' => array (
				'title' => 'List pages here',
				'title' => ($this->blogMode ? ($this->isBlogTreeRoot ? 'List blogs/pages here' : 'List blog entries') : 'Pages/sections here'),
				'tooltip' => ($this->blogMode ? ($this->isBlogTreeRoot ? 'List the blogs and other ancillary pages available' : 'List the entries in the current blog') : 'List the pages in the current section (folder) of the website'),
				'administratorsOnly' => false,
				'grouping' => 'Additional',
			),
			
			'houseStyle' => array (
				'title' => 'House style',
				'tooltip' => 'Edit the house style pages',
				'administratorsOnly' => true,
				'grouping' => 'Additional',
			),
			
			'message' => array (
				'title' => 'Send message',
				'tooltip' => 'Send a message to the administrator and/or other users of the editing system',
				'administratorsOnly' => false,
				'grouping' => 'Additional',
			),
			
			'leave' => array (
				'title' => 'Leave editing mode',
				'tooltip' => 'Go to normal viewing mode, without logging out',
				'administratorsOnly' => false,
				'grouping' => 'Additional',
				'check'		=> 'leaveLink',
				'url' => $this->liveSiteUrl . $this->page,
			),
			
			'help' => array (
				'title' => 'Tips/help',
				'tooltip' => 'Tip sheet and other help tips, as well as information about this system',
				'administratorsOnly' => false,
				'grouping' => 'Additional',
				'url' => $this->page . '?help' . ($this->helpNewWindow ? '" target="_blank' : ''),
			),
			
			'logout' => array (
				'title' => 'Log out',
				'tooltip' => 'Log out when you have finished working with the editing system to secure your account',
				'url' => ($this->logout ? $this->logout : '?logout'),
				'administratorsOnly' => false,
				'grouping' => false,
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
				#!# This line is only added because dateLimitation is the only amendable item currently - see two other comments like this below
				'check' => '!disableDateLimitation',
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
		
		# Loop through each action to perform checks for validity
		foreach ($actions as $action => $attributes) {
			
			# Disable access to an action for those marked administratorsOnly if the user is not an administrator
			if ($attributes['administratorsOnly'] && !$this->userIsAdministrator) {
				unset ($actions[$action]);
			}
			
			# If there is a special property check required (which is reversed if appended with '!'), check for that
			if (isSet ($attributes['check'])) {
				if (is_bool ($attributes['check'])) {
					if (!$attributes['check']) {
						unset ($actions[$action]);
					}
				} else if (substr ($attributes['check'], 0, 1) == '!') {
					$functionToCheck = substr ($attributes['check'], 1);
					if ($this->$functionToCheck) {
						unset ($actions[$action]);
					}
				} else {
					if (!$this->$attributes['check']) {
						unset ($actions[$action]);
					}
				}
			}
		}
		
		# Return the actions
		return $actions;
	}
	
	
	# Function to ensure there is an authenticated user
	private function validUser ()
	{
		# Ensure the user is in the list of allowed users
		if (!isSet ($this->users[$this->user])) {
			return false;
		}
		
		# Otherwise return true
		return true;
	}
	
	
	# Wrapper function to deal with the changelog
	private function logChange ($message)
	{
		# Start the HTML
		$html = '';
		
		# Prepend the message
		$message = $this->makeTimestamp () . ",{$this->user}," . $message . "\r\n";
		
		# Log the change
		#!# Move to checking writability first then remove the warning here
		if (!application::writeDataToFile ($message, $this->filestoreRoot . $this->changelog)) {
			$html .= $this->reportErrors ('There was a problem logging this change.', "The log file is at {$this->filestoreRoot}{$this->changelog} .");
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to generate a menu
	private function generateMenu ($respecifiedLocation = false)
	{
		# If the current page in the browser URL is no longer valid (e.g. a reviewed page has been deleted, or a page is made live at a respectified location), regenerate the actions list
		if ($respecifiedLocation) {
			$this->page = $this->nearestPage ($respecifiedLocation);
			$this->actions = $this->actions ();
		}
		
		# Group the actions
		foreach ($this->actions as $action => $attributes) {
			$grouping = $attributes['grouping'];
			if (!$grouping) {continue;}
			$menu[$grouping][] = $action;
		}
		
		# Define the ancilliary files
		$ancilliaryFiles = array (
			$this->pureContentTitleFile,
			$this->pureContentSubmenuFile,
			$this->pureContentMenuFile,
		);
		
		# Compile the task box HTML
		$html  = "\n\n<div id=\"administration\" class=\"graybox\">";
		$html .= "\n\n<p class=\"right\"><a href=\"" . ($this->logout ? $this->logout : '?logout') . '" title="Log out when you have finished working with the editing system to secure your account">[Log out]</a></p>';
		$html .= "\n\t<p><em>pureContentEditor</em> actions available here for <strong>{$this->user}" . ($this->userIsAdministrator ? ' (ADMIN)' : '') . '</strong>:</p>';
		$html .= "\n\t<ul id=\"administrationtypes\">";
		foreach ($menu as $group => $actions) {
			$html .= "\n\t\t<li>{$group}:";
			$html .= "\n\t\t\t<ul>";
			foreach ($actions as $action) {
				$naturalUrl = $this->chopDirectoryIndex ($this->page);
				if ($action != 'edit') {
					$delimiter = '@';
					foreach ($ancilliaryFiles as $ancilliaryFile) {
						$naturalUrl = preg_replace ($delimiter . addcslashes ($ancilliaryFile, $delimiter) . '$' . $delimiter, '', $naturalUrl);
					}
				}
				$href = (isSet ($this->actions[$action]['url']) ? $this->actions[$action]['url'] : $naturalUrl . "?{$action}");
				$liClassSelected = (($action == $this->action) ? ' class="selected"' : '');
				$classHtml = ($this->actions[$action]['administratorsOnly'] ? ' class="administrative"' : '');
				$titleHtml = " title=\"{$this->actions[$action]['tooltip']}\"";
				$html .= "\n\t\t\t\t<li{$liClassSelected}><a href=\"{$href}\"{$classHtml}{$titleHtml}>{$this->actions[$action]['title']}</a></li>";
			}
			$html .= "\n\t\t\t</ul></li>";
		}
		$html .= "\n\t</ul>";
		$html .= "\n</div>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to find the nearest page to the current (e.g. if /foo/bar/zoo.html is supplied but doesn't exist, but /foo/bar/index.html does, return that, else if /foo/index.html exists, return that)
	private function nearestPage ($page, $asSection = false)
	{
		# If the page exists, return it
		if ($this->livePage ($page) || $this->stagingPage ($page)) {
			return $page;
		}
		
		// Note that we do not check for other pages in the same folder, as the index is better over an arbitrary page
		
		# Define the main page of the site
		$root = '/';
		
		# Traverse up each directory until it is found
		while ($page != $root) {
			$directoryAbove = dirname ($page);
			$directoryAboveSlashed = $directoryAbove . ($directoryAbove == '/' ? '' : '/');
			$try = $directoryAboveSlashed . $this->directoryIndex;
			if ($this->livePage ($try) || $this->stagingPage ($try)) {
				return $directoryAboveSlashed . ($asSection ? '' : $this->directoryIndex);
			}
			$page = $directoryAbove;	// Try next up; eventually this will get to /
		}
		
		# If for some reason it is not found, return the main page of the site as a fallback
		return $root . ($asSection ? '' : $this->directoryIndex);
	}
	
	
	# Function to create a version message
	private function versionMessage ($action)
	{
		# End if the page doesn't exist (i.e. is in memory)
		if (!$this->editableFile) {return false;}
		
		# Define the message
		$versionMessage  = '';
		$addWarning = false;
		switch ($this->pageToUse) {
			case 'staging':
				$versionMessage  = 'You are ' . str_replace ('browse', 'brows', "{$action}ing") . " from an unapproved edition of this page (<span title=\"(saved at " . $this->convertTimestamp ($this->submissions[$this->stagingPage]['timestamp']) . " by " . $this->convertUsername ($this->submissions[$this->stagingPage]['username']) . ')">hover here for details</span>), the latest version available.';
				if ($this->livePage) {$versionMessage .= "<br />You can <a href=\"{$this->page}?{$action}=live\">{$action} from the live version</a> instead.";}
				break;
			case 'live':
				$addWarning = true;
				if ($this->stagingPage) {$versionMessage .= "There is a <a href=\"{$this->page}" . ($action == 'edit' ? "?{$action}" : '') . '">more recent version</a> submitted for review than the live page below.';}
				break;
			case 'particular':
				# If the page is not the latest staging page available, provide a link to that
				$versionMessage  = 'You are ' . str_replace ('browse', 'brows', "{$action}ing") . " an unapproved edition of this page (<span title=\"(saved at " . $this->convertTimestamp ($this->submissions[$this->stagingPage]['timestamp']) . " by " . $this->convertUsername ($this->submissions[$this->stagingPage]['username']) . ')">hover here for details</span>).';
				if ($this->particularPage != $this->stagingPage) {
					$addWarning = true;
					$attributes = $this->submissions[$this->stagingPage];
					$location = $attributes['directory'] . $attributes['filename'];
					$versionMessage .= "<br /><strong>A <a" . ($this->reviewPagesOpenNewWindow ? ' title="Link opens in a new window" target="_blank"': '') . " href=\"$location?review={$this->stagingPage}\">later version of this page</a> exists</strong>.";
				} else {
					if ($this->livePage) {$versionMessage .= "<br />You can <a href=\"{$this->page}?{$action}=original\">{$action} from the live version</a> instead.";}
				}
				break;
		}
		
		# End if no version message
		if (!$versionMessage) {return;}
		
		# Construct the HTML
		$html = "\n<p" . ($addWarning ? ' class="warning"' : '') . '>' . ($addWarning ? '<strong>Warning</strong>' : 'Note') . ": {$versionMessage}</p>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a logout link
	private function logout ()
	{
		# Create the logout link
		$html  = "\n<p class=\"warning\">" . ($this->logout ? "<a href=\"{$this->logout}\">Please click here to log out.</a>" : 'To log out, close all instances of your web browser.') . '</p>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a help section
	private function help ()
	{
		# Create the logout link
		$html  = "\n\n" . '<div id="purecontenteditorhelp">';
		$html .= "\n<h1>Tips/help/about</h1>";
		$html .= "\n<p>Welcome to the pureContentEditor! Use of this system is intended to be largely self-explanatory: you can browse around the site as normal, and perform various actions using the menu buttons above.</p>";
		$html .= "\n<p>When you are finished, please use the 'Log out' button in the menu above, to protect the integrity of your account.</p>";
		$html .= "\n<h2>Tips</h2>";
		$html .= "\n" . "<p><a href=\"{$this->tipsUrl}\" target=\"_blank\"><strong>Help/tips on using the editor</strong></a> are available.</p>";
		$html .= "\n<h2>Richtext editor user guide</h2>";
		$html .= "\n" . "<p>There is also a comprehensive <a href=\"http://docs.cksource.com/CKEditor_3.x/Users_Guide\" target=\"_blank\">user guide for the Microsoft Word-style editor part</a> of the system (the richtext editor).</p>";
		$html .= "\n" . "<p>The HTML submitted in a richtext field will be cleaned on submission. If you don't want this to happen, go into source mode, and add to the <strong>start</strong>, the following: " . htmlspecialchars ($this->nofixTag) . '</p>';
		$html .= "\n<h2>If you are having problems</h2>";
		$html .= "\n<p>To get help on use of the system, <a href=\"{$this->page}?message\">contact an administrator</a> of the system.</p>";
		$html .= "\n<h2>About</h2>";
		$html .= "\n" . '<p>This system runs on the <strong>pureContentEditor</strong> software, which has been written by Martin Lucas-Smith, University of Cambridge. It is released under the <a href="http://opensource.org/licenses/gpl-license.php" target="_blank">GNU Public License</a>. The system is free, is installed at your own risk and no support is provided by the author, except where explicitly arranged.</p>';
		$html .= "\n" . '<p>It makes use of the DHTML editor component <a href="http://ckeditor.com/" target="_blank">CKEditor</a>, which is also licenced under the GPL.</p>';
		$html .= "\n" . '<p><a href="http://download.geog.cam.ac.uk/projects/purecontenteditor/" target="_blank">Technical documentation and information on new releases</a> on the pureContentEditor software is available.</p>';
		$html .= "\n<p>This is version <em>{$this->version}</em> of the pureContentEditor.</p>";
		$html .= "\n" . '</div>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to load pages as if loading normally using pureContent
	private function browse ()
	{
		# Start the HTML
		$html = '';
		
		# If the page is being aliased, stop at this point
		if ($this->pageIsBeingAliased) {
			$html .= "\n<p class=\"warning\">Note: <strong>this page cannot be browsed or edited</strong> because it is being magically mirrored from another site. Please <a href=\"{$this->page}?message\">contact the server administrator</a> if you need to edit it.</p>";
			return $html;
		}
		
		# If the page is missing, give a 404
		if ($this->pageIs404 || (!$this->livePage && !$this->stagingPage)) {
			application::sendHeader (404);
			$html  = "\n" . '<h1>Page not found</h1>';
			if ($this->page == $this->currentDirectory . $this->directoryIndex) {
				#!# Not clear how this line and its container differ from the !$this->directoryContainsIndex () check a few lines down
				$html .= "\n" . '<p>There is no page <em>' . htmlspecialchars ($this->page) . '</em>. Do you want to <a href="' . htmlspecialchars ($this->page) . '?edit">create the front page of this section</a>, since it is currently empty?</p>';
			} else {
				#!# If $this->page is "/foo/bar" then this link will do nothing
				$html .= "\n" . '<p>There is no page <em>' . htmlspecialchars ($this->page) . '</em>. Do you want to <a href="' . htmlspecialchars ($this->page) . '?edit">create a new page</a> with that name here?</p>';
			}
			
			return $html;
		}
		
		# If there is no directory index, state that this is required
		if (!$this->directoryContainsIndex ()) {
			$html = "<p class=\"warning\">This section currently contains no front page ({$this->directoryIndex}). You need to <a href=\"{$this->currentDirectory}{$this->directoryIndex}?edit\">create an index page for this section</a> before creating other pages.</p>";
			return $html;
		}
		
		# Give a message for what file is being browsed, including the timestamp
		$html .= $this->versionMessage (__FUNCTION__);
		
		# Check for the presence of PHP instructions or aliasing
		if ($this->pageContainsPhp) {
			
			# Administrators get a warning that this can only be edited as HTML rather than in WYSIWYG mode
			if ($this->userIsAdministrator) {
				$message = "\n<p class=\"warning\">This page cannot be edited using the normal visual mode because it contains programming code (PHP). However, as you are an administrator, you are able to <a href=\"{$this->page}?edit\">edit the HTML/PHP in code mode</a>.</p>";
			} else {
				$message = "\n<p class=\"warning\">This page cannot be edited here because it contains programming code (PHP). Please <a href=\"{$this->page}?message\">contact the server administrator</a> if you need to edit it.</p>";
			}
			
			# Give a message that the page cannot be edited with this system
			$html .= $message;
			
			# Change the working directory, in case there are local includes
			chdir (str_replace ('\\', '/', dirname ($this->editableFile)));
		}
		
		# Import the globals environment into local scope
		extract ($GLOBALS);
		
		# Determine if there is a pureContent header/footer file to show
		#!# Local includes in these files are not supported at present
		$headermenuFile = $this->liveSiteRoot . $this->currentDirectory . $this->pureContentHeadermenuFile;
		$headermenu = (is_readable ($headermenuFile) ? $headermenuFile : false);
		$footermenuFile = $this->liveSiteRoot . $this->currentDirectory . $this->pureContentFootermenuFile;
		$footermenu = (is_readable ($footermenuFile) ? $footermenuFile : false);
		
		# Capture the contents
		ob_start ();
		if ($headermenu) {include ($headermenu);}
		include ($this->editableFile);
		if ($footermenu) {include ($footermenu);}
		$pageContents = ob_get_clean ();
		
		# Show the contents
		$html .= "\n<hr />\n</div>\n\n\n";
		$html .= $pageContents;
		$html .= "\n\n\n<div>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine whether a location is potentially writable; for instance, if a file would need to go at /path/to/foo/index.html and /path/to/ was all that existed, then the check would be for writability of /path/to/
	private function treesPotentiallyWritable ($location, &$html)
	{
		# Do the check for each of the roots
		$roots = array ('filestore' => $this->filestoreRoot, 'live site' => $this->liveSiteRoot);
		foreach ($roots as $description => $root) {
			if (!application::directoryIsWritable ($location, $root . '/')) {
				$html .= $this->reportErrors ("Unfortunately, the operation failed - the {$description} is not set up properly.", "The path to {$root}{$location} is not writable.");
				return false;
			}
		}
		
		# Return success, as all roots have a writeable path within them
		return true;
	}
	
	
	# Function to edit/create the breadcrumb
	private function breadcrumb ()
	{
		return $this->edit ();
	}
	
	
	# Function to edit/create the submenu
	private function submenu ()
	{
		return $this->edit ();
	}
	
	
	# Function to edit/create the sidebar
	private function sidebar ()
	{
		return $this->edit ();
	}
	
	
	# Function to edit the page
	private function edit ()
	{
		# Start the HTML
		$html = '';
		
		# Ensure that the tree is writable, or end
		if (!$this->treesPotentiallyWritable ($this->currentDirectory, $html)) {
			return $html;
		}
		
		
		# If the current section does not exist, require section creation as high up the tree as necessary
		if ($requireSectionCreationHtml = $this->requireSectionCreation ()) {
			$html .= $requireSectionCreationHtml;
			return $html;
		}
		
		# If there is no directory index, state that this is required
		$pagename = basename ($this->page);
		$requestIsIndex = ($pagename == $this->directoryIndex);
		$houseStyleFiles = $this->getHouseStyleFiles ();
		$forceIndexPageCreation = (!$requestIsIndex && !$this->directoryContainsIndex () && !array_key_exists ($this->page, $houseStyleFiles));
		if ($forceIndexPageCreation) {
			$html = "<p class=\"warning\">This section currently contains no front page ({$this->directoryIndex}). You need to <a href=\"{$this->currentDirectory}{$this->directoryIndex}?edit\">create an index page for this section</a> before creating other pages.</p>";
			return $html;
		}
		
		# Check that the proposed filename is valid
#!# Needs to check if the page already exists - should allow editing of a badly-named existing page
		$regexp = $this->validPageNameRegexp ();
		$delimiter = '@';
		if (!preg_match ($delimiter . addcslashes ($regexp, $delimiter) . $delimiter, $pagename) && !array_key_exists ($this->page, $houseStyleFiles)) {
			$html = "<p class=\"warning\">The pagename you requested " . htmlspecialchars ($pagename) . " is invalid. Please go to <a href=\"{$this->currentDirectory}?newPage\">create a new page</a> again.</p>";
			return $html;
		}
		
		# Create the form itself
		$form = new form (array (
			'name' => 'purecontent',
			'displayTitles' => ($this->typeOfFile == 'titleFile'),
			'displayDescriptions' => ($this->typeOfFile == 'titleFile'),
			'displayColons' => true,
			'submitButtonText' => 'Submit page for approval' . ($this->userCanMakeFilesLiveDirectly ? ' / Make live' : ''),
			'formCompleteText' => false,
			'nullText' => 'Select which administrator to inform of this submission:',
			// 'unsavedDataProtection' => true, // Seemingly has no effect on the richtext area unfortunately
		));
		
		# Add a reminder when in blog mode
		$heading  = '';
		if ($this->blogMode) {
			$heading .= $this->blogModeReminder;
		}
		
		# Give a message for what file is being edited, and if necessary a PHP care warning
		$heading .= $this->versionMessage (__FUNCTION__);
		$heading .= ($this->pageContainsPhp ? "</p>\n<p class=\"warning\">Take care when editing this page as it contains programming code (PHP)." : '');
		
		# If the file is a technical file, then replace the heading with a strong warning
		$textMode = false;
		if ($this->matchLocation ($this->technicalFileLocations, $this->page)) {
			$textMode = true;
			$heading  = "\n<p class=\"warning\"><strong>Take special care when editing this page as changes to this technical file could disrupt the entire site.</strong></p><p>Alternatively, you may wish to <a href=\"/?houseStyle\">return to the list of house style / technical files</a>.</p>";
		}
		
		# Add the heading
		if ($heading) {$form->heading ('', $heading);}
		
		# Pre-process the contents
		$contents = $this->preprocessContents ($this->editableFileContents);
		
		# Give the correct type of editing box
		switch ($this->typeOfFile) {
			
			# For a title file, show a standard input box
			case 'titleFile':
				
				# Input widget
				$form->input (array (
					'name'			=> 'content',
					'title'			=> 'Title for the section (title file)',
					'description'	=> "Please capitalise correctly. This is the text that will appear in the breadcrumb trail (the 'You are in...' line) and must not be too long.",
					'default'		=> $contents,
					'required'		=> true,
					'autofocus' 	=> true,
					'size'			=> 50,
				));
				break;
				
			# For a menu or a normal page
			case 'submenuFile':
			case 'sidebarFile':
			case false:
			default:
				
				# If the page doesn't exist, then select the template
				$editorToolbarSet = $this->richtextEditorToolbarSet;
				switch ($this->typeOfFile) {
					case 'submenuFile':
						$this->richtextEditorWidth = 400;
						$this->richtextEditorHeight = 400;
						$editorToolbarSet = $this->richtextEditorToolbarSetBasic;
						if (!$this->editableFile) {
							$contents = $this->newSubmenuTemplate;
						}
						break;
					case 'sidebarFile':
						$this->richtextEditorWidth = 400;
						$this->richtextEditorHeight = 500;
						if (!$this->editableFile) {
							$contents = $this->newSidebarTemplate;
						}
						break;
					default:
						if (!$this->editableFile) {
							$title = ($this->attribute ? htmlspecialchars ($this->attribute) : $this->newPageTemplateDefaultTitle);
							$contents = str_replace ('%title', $title, $this->newPageTemplate);
						}
						break;
				}
				
				# If the page contains PHP (and thus the user is an administrator to have got this far in the code), give a text area instead
				if ($this->pageContainsPhp || $textMode) {
					
					$form->textarea (array (
						'name'		=> 'content',
						'title'		=> 'Page content',
						'required'	=> true,
						'cols'		=> $this->textareaEditorWidth,
						'rows'		=> $this->textareaEditorHeight,
						'default'	=> $contents,
						'wrap'		=> 'off',
					));
					
				} else {
					
					# Define initial replacements
					$replacements = array (
						" href=\"{$this->editSiteUrl}/"	=> " href=\"{$this->liveSiteUrl}/",	// Ensure images are not prefixed with the edit site's URL
						" href=\"{$this->liveSiteUrl}/"	=> " href=\"/",	// Ensure images are not prefixed with the edit site's URL
						" src=\"{$this->liveSiteUrl}/"	=> 'src="/',	// Ensure images are not prefixed with the current site's URL
						" href=\"http://{$this->liveSiteUrl}:{$this->editHostPort}/"	=> ' href=\"/',	// Workaround for Editor port reassignment bug
					);
					
					# Create the richtext field
					$form->richtext (array (
						'name'							=> 'content',
						'title'							=> 'Page content',
						'required'						=> true,
						'default'						=> $contents,
						'autofocus'						=> true,
						'editorBasePath'				=> $this->richtextEditorBasePath,
						'editorToolbarSet'				=> $editorToolbarSet,
						'editorFileBrowser'				=> $this->richtextEditorFileBrowser,	// Path of file browser (or false to disable)
						'editorFileBrowserStartupPath'	=> ($this->currentDirectory == '/' ? '/images/' : $this->currentDirectory),
						'editorFileBrowserACL'			=> $this->cKFinderAccessControl (),	// Access Control List (ACL) passed to CKFinder in the format it requires
						'width'							=> $this->richtextEditorWidth,
						'height'						=> $this->richtextEditorHeight,
						'config.contentsCss'			=> $this->richtextEditorEditorAreaCSS,	// Or array of stylesheets
						'config.bodyId'					=> ($this->bodyAttributes ? pureContent::bodyAttributesId () : false),
						'config.bodyClass'				=> ($this->bodyAttributes ? pureContent::bodyAttributesClass () . ' editorwindowstyle' : false),
						'allowCurlyQuotes'				=> $this->allowCurlyQuotes,
						'protectEmailAddresses'			=> $this->protectEmailAddresses,	// Whether to obfuscate e-mail addresses
						'externalLinksTarget'			=> $this->externalLinksTarget,		// The window target name which will be instanted for external links (as made within the editing system) or false
						'directoryIndex' 				=> $this->directoryIndex,			// Default directory index name
						'imageAlignmentByClass'			=> $this->imageAlignmentByClass,	// Replace align="foo" with class="foo" for images
						'replacements'					=> $replacements,
						'nofixTag'						=> $this->nofixTag,
						'removeComments'				=> $this->removeComments,
						'disallow'						=> ($this->allImagesRequireAltText ? array ('<img ([^>]*)alt=""([^>]*)>' => 'All images must have alternative text supplied, for accessibility reasons. Please correct this by right-clicking on the image, selecting \'Image Properties\' and entering a description of the image in the field marked \'Alternative Text\'.') : false),	// Images without alternative text
					));
				}
		}
		
		# Select the administrator to e-mail
		$form->select (array (
		    'name'            => 'administrators',
		    'values'            => $this->administratorSelectionList ($enableNoneOption = $this->userCanMakeFilesLiveDirectly),
		    'title'                    => 'Administrator to inform',
		    'required'        => 1,
			'default' => ($this->userCanMakeFilesLiveDirectly ? '_none' : '_all'),
		));
		
		# Allow administrators to make live directly
		if ($this->userCanMakeFilesLiveDirectly) {
			$makeLiveDirectlyText = 'Make live directly (do not send for moderation)';
			$form->checkboxes (array (
			    'name'			=> 'preapprove',
			    'values'			=> array ($makeLiveDirectlyText,),
			    'title'					=> $makeLiveDirectlyText,
				'default'		=> ($this->makeLiveDefaultChecked ? array ($makeLiveDirectlyText) : false),
			));
		}
		
		# Show and process the form; end if not submitted
		if (!$result = $form->process ($html)) {
			return $html;
		}
		
		# Get the submitted content
		$content = $result['content'];
		
		# Determine whether to approve directly
		$approveDirectly = ($this->userCanMakeFilesLiveDirectly ? $result['preapprove'][$makeLiveDirectlyText] : false);
		
		# Save the file to the filestore or the live site as appropriate
		if ($approveDirectly) {
			$html .= $this->makeLive ($this->page, $content, $madeLiveOk, $approveDirectly);
			if (!$madeLiveOk) {
				return $html;
			}
			$message = "A page has been directly made live at:\n{$this->liveSiteUrl}" . $this->chopDirectoryIndex ($this->page);
			$subjectSuffix = 'page directly made live';
		} else {
			
			#!# Ideally there needs to be a different error message here if the problem was that the file was actually just empty
			
			# Create the file by supplying the complete file location and filename
			if (!$filename = application::createFileFromFullPath ($this->filestoreRoot . $this->page, $content, $addStamp = true, $this->user)) {
				$html .= $this->reportErrors ('Unfortunately, the operation failed - there was a problem creating the new file in the filestore.', "This new file would have been at $this->page on the live site.");
				return $html;
			} else {
				
				# Log the change
				$html .= $this->logChange ('Submitted ' . ($this->blogMode ? 'blog posting' : 'page') . " {$this->page}");
				
				# Construct a confirmation message
				$delimiter = '@';
				$message = 'A ' . ($this->blogMode ? 'blog posting' : 'page') . " has been submitted for the location:\n{$this->page}\n\nPlease log on to the editing system to moderate it, at:\n\n{$this->editSiteUrl}" . preg_replace ($delimiter . '^' . addcslashes ($this->filestoreRoot, $delimiter) . $delimiter, '', $filename) . '?review';
				$subjectSuffix = ($this->blogMode ? 'blog posting' : 'page') . ' submitted for moderation';
			}
		}
		
		# Delete the version on which the pre-edited page was based if it is based on a particular page (which, by definition, lives in the filestore)
		if ($this->particularPage) {
			if (!@unlink ($this->editableFile)) {
				$html .= $this->reportErrors ('There was a problem deleting the pre-edited page.', "The filename was {$this->editableFile} .");
				return $html;
			}
			$html .= $this->logChange ("Pre-edited " . ($this->blogMode ? 'blog posting' : 'page') . " $this->page deleted from filestore.");
			$html .= "\n<p class=\"success\">The pre-edited " . ($this->blogMode ? 'blog posting' : 'page') . " from which this new version was created has been deleted from the filestore.</p>";
		}
		
		# Display the submitted content and its HTML version as a confirmation
		$html .= $this->showMaterial ($content);
		
		# Select which administrator(s) to e-mail
		if (isSet ($message)) {
			switch ($result['administrators']) {
				case '_none':
					break;
				case '_all':
					$html .= $this->sendMail ($this->administrators, $message, $subjectSuffix);
					break;
				default:
					$html .= $this->sendMail ($result['administrators'], $message, $subjectSuffix);
			}
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to provide an access Control List (ACL) to be passed to CKFinder in the format it requires
	private function cKFinderAccessControl ()
	{
		# End if not using CKFinder
		if (!$this->richtextEditorFileBrowser) {return false;}
		
		# Get the current user's permissions
		if ($this->userIsAdministrator) {
			$currentUserPermissions = array ('/*');
		} else {
			$currentUserPermissions = $this->convertPermissionsList ($this->currentUserPermissions, true);
		}
		
		# Define default read-only rights
		$defaultRights = array (
			'role' => '*',
			'resourceType' => '*',
			'folder' => '/',
			
			'folderView' => true,
			'folderCreate' => false,
			'folderRename' => false,
			'folderDelete' => false,
			
			'fileView' => true,
			'fileUpload' => false,
			'fileRename' => false,
			'fileDelete' => false,
		);
		
		# Loop through each permission
		foreach ($currentUserPermissions as $location) {
			
			# Deal with the three cases of /, /*, /filename.html
			#!# Limited implementation of /filename.html currently - results in greater rights than intended at present
			$treeRights = false;
			switch (substr ($location, -1)) {
				case '*':
					$location = substr ($location, 0, -1);	// Chop off the *
					$treeRights = true;
					break;
				case '/':
					
					break;
				default:
					$location = dirname ($location) . '/';	// Chop off the filename
					break;
			}
			
			# Skip if the location is already covered, i.e. there is an overlapping right
			if (isSet ($cKFinderAccessControl[$location])) {continue;}
			
			# Add this permission
			$cKFinderAccessControl[$location] = array (
				'role' => '*',
				'resourceType' => '*',
				'folder' => $location,
				
				'folderView' => true,
				'folderCreate' => $treeRights,
				'folderRename' => false,	// This is a broken model - it works differently to the Unix model, which means that renaming only applies to contained items, not the container itself plus tree
				'folderDelete' => false,	// Ditto broken model
				
				'fileView' => true,
				'fileUpload' => true,
				'fileRename' => true,
				'fileDelete' => true,
			);
			
			# If at the top level, deny file addition to force files to be within the structure rather than dumped in the root area
			if ($location == '/') {
				$cKFinderAccessControl[$location]['fileUpload'] = false;
			}
			
			# If tree rights, add the child privileges
			if ($treeRights) {
				if ($childFolders = directories::listContainedDirectories ($this->liveSiteRoot . $location)) {
					foreach ($childFolders as $folder) {
						
						# Skip specific folders
						if (($folder == '_thumbnails') || ($folder == 'sitetech')) {continue;}
						
						# Define the folder name
						$folder = $location . $folder . '/';
						
						# Skip if the location is already covered, i.e. there is an overlapping right; pureContentEditor assigns higher permissions first
						if (isSet ($cKFinderAccessControl[$folder])) {continue;}
						
						# Add child permissions
						$cKFinderAccessControl[$folder] = array (
							'role' => '*',
							'resourceType' => '*',
							'folder' => $folder,
							
							'folderView' => true,
							'folderCreate' => true,
							'folderRename' => true,
							'folderDelete' => true,
							
							'fileView' => true,
							'fileUpload' => true,
							'fileRename' => true,
							'fileDelete' => true,
						);
					}
				}
			}
		}
		
		# Add read-only access across the entire site if a specific permission hasn't already been set
		if (!isSet ($cKFinderAccessControl['/'])) {
			$cKFinderAccessControl['/'] = $defaultRights;
		}
		
		//application::dumpData ($currentUserPermissions);
		//application::dumpData ($cKFinderAccessControl);
		
		# Return the ACL
		return $cKFinderAccessControl;
	}
	
	
	# Function to show a page (with the HTML version after)
	private function showMaterial ($content, $class = 'success')
	{
		# Construct the HTML
		$html  = "\n<p class=\"{$class}\">The submitted material was as follows:</p>";
		$html .= "\n<hr />";
		$html .= "\n</div>";
		$html .= "\n\n\n";
		$html .= ($this->typeOfFile == 'page' ? $content : "<pre>{$content}</pre>");
		$html .= "\n\n\n" . '<div id="purecontenteditorresult">';	// Admittedly, using ID here is not strictly valid HTML, but it keeps the stylesheet simpler
		
		# Continue the HTML if not a title file
		if ($this->typeOfFile != 'titleFile') {
			$html .= "\n<hr />";
			$html .= "\n<p class=\"{$class}\">The submitted underlying HTML was as follows:</p>";
			$html .= "\n<hr />";
			$html .= "\n<pre>";
			$content = htmlspecialchars ($content);
			$html .= ($this->wordwrapViewedSubmittedHtml ? wordwrap ($content) : $content);
			$html .= "\n</pre>";
			$html .= "\n<hr />";
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to change/create header images
	private function headerimage ()
	{
		# Start the HTML
		$html  = '';
		
		# Ensure the directory exists
		$imageStore = $this->liveSiteRoot . $this->pureContentHeaderImageStore;
		
		# Determine the supported file type
		$extension = strtolower (pathinfo ($this->pureContentHeaderImageFilename, PATHINFO_EXTENSION));
		
		# Get a list of all the current headers, ordered by most recent first
		$currentImages = directories::listFiles ($imageStore, $extension, $directoryIsFromRoot = true);
		$currentImages = application::natsortField ($currentImages, 'time');
		$currentImages = array_reverse ($currentImages, true);
		
		# Determine whether a specific size is required
		$specificSizeRequired = ($this->pureContentHeaderImageWidth && ctype_digit ((string) $this->pureContentHeaderImageWidth) && $this->pureContentHeaderImageHeight && ctype_digit ((string) $this->pureContentHeaderImageHeight));
		
		# Filter for the correct size if required
		if ($specificSizeRequired) {
			foreach ($currentImages as $file => $attributes) {
				list ($width, $height, $type, $imageSize) = getimagesize ($imageStore . $file);		// NB is_readable() has already been done by directories.php
				if (($width != $this->pureContentHeaderImageWidth) || ($height != $this->pureContentHeaderImageHeight)) {
					unset ($currentImages[$file]);
				}
			}
		}
		
		# If an image has been selected, put it in place
		if ($this->attribute) {
			$delimiter = '@';
			if (preg_match ($delimiter . '\.' . addcslashes ($extension, $delimiter) . '$' . $delimiter, $this->attribute)) {
				if (array_key_exists ($this->attribute, $currentImages)) {	// Avoids any hack attempts
					
					# Move the image to the right position
					$currentLocation = $this->attribute;
					$newLocation = $this->currentDirectory . $this->pureContentHeaderImageFilename;
					if (!copy ($imageStore . $currentLocation, $this->liveSiteRoot . $newLocation)) {
						$errorsHtml  = $this->reportErrors ('There was a problem copying the header file to the section.', "The header image \"{$this->attribute}\" could not be copied to {$this->currentDirectory} .");
						return $errorsHtml;
					}
					
					# Confirm success
					$link = $this->liveSiteUrl . $this->chopDirectoryIndex ($this->page);
					$html  = "\n<p><img src=\"/images/general/tick.gif\" alt=\"Tick\" border=\"0\"> The new header image has been added to the live site.</p>";
					$html .= "\n<p>View the <a target=\"_blank\" href=\"" . $link . "\">front page of this section, showing the new header</a>, in a new window.</p>";
					$html .= "\n<p><a target=\"_blank\" href=\"{$link}\"><img src=\"{$newLocation}\" alt=\"Header image\" border=\"0\" /></a></p>";
					$html .= "\n<br />";
					$html .= "\n<p><a href=\"{$this->currentDirectory}?headerimage\">Replace with a different image?</a></p>";
					
					# Log the change
					$html .= $this->logChange ("header image {$this->attribute} used as header at {$this->currentDirectory}.");
					
					# Refresh the page so that the new header is visibly shown
					application::sendHeader (302, $this->editSiteUrl . $this->currentDirectory);
					
					# Return the HTML
					return $html;
				}
			}
		}
		
		# Show an image upload form
		$imageUploadForm = $this->imageUploadForm ($imageStore, $extension, $specificSizeRequired, array_keys ($currentImages));
		$html .= "\n<div class=\"graybox\">" . $imageUploadForm . "\n</div>";
		
		# End if no images
		if (!$currentImages) {
			$html .= "\n<p>There are no current images.</p>";
			return $html;
		}
		
		# Create a gallery of all images
		$totalImages = count ($currentImages);
		$html .= "\n<p>There " . ($totalImages == 1 ? 'is currently one image' : "are currently {$totalImages} images") . ":</p>";
		$html .= "<hr />";
		foreach ($currentImages as $file => $attributes) {
			$html .= "<br />";
			$html .= "\n<h3><strong>" . htmlspecialchars ($attributes['name']) . "</strong> [<a href=\"{$this->currentDirectory}?headerimage=" . htmlspecialchars (urlencode ($file)) . '">Use this one</a>]:</h3>';
			$html .= "\n<p><a href=\"{$this->currentDirectory}?headerimage=" . htmlspecialchars (urlencode ($file)) . "\"><img src=\"{$this->pureContentHeaderImageStore}" . htmlspecialchars ($file) . "\" alt=\"Header image\" border=\"0\" /></a></p>";
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create an image upload form
	private function imageUploadForm ($imageStore, $extension, $specificSizeRequired, $currentImages)
	{
		# Start the HTML
		$html = '';
		
		# If the 'true' attribute is set then show confirmation
		if ($this->attribute == 'true') {
			$html  = "\n<p><img src=\"/images/general/tick.gif\" alt=\"Tick\" border=\"0\"> The image has been successfully uploaded and is shown below. [<a href=\"{$this->currentDirectory}?headerimage\">Add another</a>?]</p>";
			$html .= "\n<p><strong>Select it below to confirm that you wish to use it for this section.</strong></p>";
			return $html;
		}
		
		# Describe the restrictions
		$restrictions = "The image <strong>must</strong> have a <strong>.{$extension} extension</strong>" . ($specificSizeRequired ? " and must be exactly <strong>{$this->pureContentHeaderImageWidth}px</strong> by <strong>{$this->pureContentHeaderImageHeight}px</strong>" : '') . '.';
		
		# Create an upload form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'formCompleteText' => false,
			'div' => false,
			'displayRestrictions' => false,
			'requiredFieldIndicator' => false,
			'displayColons' => true,
		));
		$form->heading (2, 'Add an image');
		$form->heading ('p', $restrictions);
		$form->upload (array (
			'name'				=> 'image',
			'title'				=> 'Select the image',
			'directory'			=> $imageStore,
			'allowedExtensions'	=> array ($extension),
			'required'			=> true,
			'forcedFileName'	=> $this->user,		// To prevent clashes when uploading
			'flatten'		=> true,
			'autofocus'		=> true,
		));
		$form->input (array (
			'name'			=> 'name',
			'title'			=> 'Give it clear description',
			'required'		=> true,
			'maxlength'		=> $this->maximumFileAndFolderNameLength,
			'size'			=> 50,
			'maxlength'		=> 40,
			'regexp'		=> "^([a-zA-Z0-9 -]{1,40})$",
			'placeholder'	=> 'Characters: a-z A-Z 0-9 spaces and hyphens only',
		));
		if ($result = $form->process ($html)) {
			
			# Prevent clashing filenames
			$uploadedFilename = $this->user . '.' . $extension;
			$requestedFilename = $result['name'] . '.' . $extension;
			if (in_array ($requestedFilename, $currentImages)) {
				unlink ($imageStore . $uploadedFilename);
				$html = "<p class=\"warning\">Sorry, an image with that name already exists, so the one you selected has not been added. Please <a href=\"{$this->page}?headerimage\">try again</a>.</p>";
				return $html;
			}
			
			# Check the size if required
			if ($specificSizeRequired) {
				list ($width, $height, $type, $imageSize) = getimagesize ($imageStore . $uploadedFilename);
				if (($width != $this->pureContentHeaderImageWidth) || ($height != $this->pureContentHeaderImageHeight)) {
					unlink ($imageStore . $uploadedFilename);
					$html = "<p class=\"warning\">The image was the wrong size, so the one you selected has not been added. Please <a href=\"{$this->page}?headerimage\">try again</a>.</p>";
					return $html;
				}
			}
			
			# Move the file
			rename ($imageStore . $uploadedFilename, $imageStore . $requestedFilename);
			
			# Refresh the page, which will show the recently-uploaded image at the top
			$redirectTo = "{$this->currentDirectory}?headerimage=true";
			application::sendHeader (302, $this->editSiteUrl . $redirectTo);
			return false;
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a new section
	private function section ()
	{
		# Start the HTML
		$html = '';
		
		# If the current section does not exist, require section creation as high up the tree as necessary
		if ($requireSectionCreationHtml = $this->requireSectionCreation ()) {
			$html .= $requireSectionCreationHtml;
			return $html;
		}
		
		# Get the current folders for the live and staging areas
		$currentFolders = $this->getCurrentFoldersHere ();
		
		# Define a regexp for the current page
		$currentFoldersRegexp = $this->currentPagesFoldersRegexp ($currentFolders);
		
		# Hack to get the current page submitted, used for a link if necessary
		$folderSubmitted = ((isSet ($_POST['form']) && isSet ($_POST['form']['urlslug'])) ? htmlspecialchars ($_POST['form']['urlslug']) . '/' : '');
		
		# Form for the new folder
		$form = new form (array (
			'display' => 'paragraphs',
			'displayDescriptions'	=> true,
			'displayRestrictions'	=> false,
			'formCompleteText'	=> false,
			'submitButtonText'		=> 'Create new section (folder)',
			'requiredFieldIndicator' => false,
		));
		$form->heading ('', 
			($this->isBlogTreeRoot ? 
				'<p>Create a new blog here.' . ($currentFolders ? ' Current blogs are <a href="#currentfolders">listed below</a>.' : '') . '</p>'
			: 
				'<p class="information"><strong>A section is a set of pages on the same topic</strong> (as distinct from a page within an existing topic area).' . ($currentFolders ? ' <a href="#current">Current folders</a> are listed below.' : '') . '</p>'
			)
			. '<br />'
		);
		
		# Title
		$form->heading (3, 'What is the title of this section?');
		$description = "
		<ul class=\"comment\" style=\"margin-bottom: 2.4em;\">
			<li>Capitalise this at the start, like a normal sentence</li>
			<li>Keep it fairly short</li>
		</ul>
		";
		$form->input (array (
			'name'			=> 'title',
			'title'			=> ($this->isBlogTreeRoot ? "Title of blog (e.g. 'Webmaster's blog')" : "Title, which appears in the 'You are in&hellip;' line near the top of the page"),
			'description'	=> $description,
			'required'		=> true,
			'size'			=> 35,
			'placeholder'	=> 'Title of this section',
			'autofocus' => true,
		));
		
		# URL slug
		$form->heading (3, 'Now choose a short one-word folder name');
		$description = "
		<ul class=\"comment\">
			<li><strong>All lower-case, a-z and 0-9 only</strong> (no punctuation)</li>
			<li><strong>Avoid abbreviations</strong>: make it guessable and self-explanatory</li>
			<!--<li>{$this->maximumFileAndFolderNameLength} characters maximum</li>-->
			<li>If necessary, run two words together</li>
			<li>Consider permanence (e.g. 'contacts' for a telephone number section - it could have addresses later)</li>
			<li>Folders form a hierarchical structure</li>
		</ul>
		";
		$form->input (array (
			'name'			=> 'urlslug',
			'title'					=> ($this->isBlogTreeRoot ? 'New folder name for blog' : 'Folder name for the section'),
			'description'	=> $description,
			'required'				=> true,
			'maxlength' => $this->maximumFileAndFolderNameLength,
			'size' => ceil (0.8 * $this->maximumFileAndFolderNameLength),
			'regexp'				=> "^[a-z0-9]{1,{$this->maximumFileAndFolderNameLength}}$",
			'disallow' => ($currentFolders ? array ($currentFoldersRegexp => "Sorry, <a href=\"{$folderSubmitted}\">a " . ($this->isBlogTreeRoot ? 'blog' : 'folder') . " of that name</a> already exists, as shown in the list below. Please try another.") : false),
			'default' => $this->attribute,
			'prepend' => $this->currentDirectory . ' ',
			'append'  => '/',
			'placeholder'	=> 'foldername',
		));
		
		# Show the form and get any results
		$result = $form->process ($html);
		
		# Show the folders which currently exist if there are any
		if (!$result) {
			$html .= "\n<h2 id=\"current\">Current folders</h2>";
			$html .= $this->listCurrentResources ($currentFolders, 'folders');
			return $html;
		}
		
		# Get the new folder location
		$new = $result['urlslug'];
		
		# Create the directory
		if (!$this->makeDirectory ($this->filestoreRoot . $this->currentDirectory . $new . '/')) {
			$html .= $this->reportErrors ('Unfortunately, the operation failed - there was a problem creating folders in the filestore; no index page or section title have been created either because of this.', "The proposed new directory was {$this->currentDirectory}{$new}/");
			return $html;
		}
		
		# Log the change
		$html .= $this->logChange ("Created folder {$this->currentDirectory}{$new}/");
		
		# Create the title file
		$titleFileLocation = $this->filestoreRoot . $this->currentDirectory . $new . '/' . $this->pureContentTitleFile;
		if (!application::createFileFromFullPath ($titleFileLocation, $result['title'], $addStamp = true, $this->user)) {
			$html .= $this->reportErrors ('Unfortunately, the operation failed - there was a problem creating the title file in the filestore; the new index page has also not been created.');
			return $html;
		}
		
		# Log the change
		$html .= $this->logChange ("Created title file {$this->currentDirectory}{$new}{$this->pureContentTitleFile}");
		
/*
		# Determine the template
		list ($this->blogMode, $this->isBlogTreeRoot) = $this->blogMode ($this->currentDirectory . $new . '/');
		$template = $this->templateMark . ($this->blogMode ? ($this->isBlogTreeRoot ? $this->newBlogTreeRootTemplate : str_replace ('%title', $result['title'], $this->newBlogIndexTemplate)) : str_replace ('%title', $result['title'], $this->newPageTemplate));
		
		# Create the front page
		$frontPageLocation = $this->filestoreRoot . $this->currentDirectory . $new . '/' . $this->directoryIndex;
		if (!application::createFileFromFullPath ($frontPageLocation, $template, $addStamp = true, $this->user)) {
			$html .= $this->reportErrors ('Unfortunately, the operation failed - there was a problem creating the new directory index in the filestore.');
			return $html;
		}
		
		# Log the change
		$html .= $this->logChange ("Created template index page {$this->currentDirectory}{$new}/{$this->directoryIndex}");
*/
		
		# Confirm success
		$redirectTo = "{$this->currentDirectory}{$new}/{$this->directoryIndex}?edit=" . urlencode ($result['title']);
		application::sendHeader (302, $this->editSiteUrl . $redirectTo);
		$html .= "<p class=\"success\">You can now <a href=\"{$redirectTo}\">edit the front page of this new section</a>.</p>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Wrapper function to make a directory, ensuring that windows backslashes are converted and that recursiveness is dealt with
	private function makeDirectory ($newDirectory)
	{
		# Ensuring that / becomes \ on Windows
		if (strstr (PHP_OS, 'WIN')) {$newDirectory = str_replace ('/', '\\', $newDirectory);}
		
		# If the directory exists, return success
		if (is_dir ($newDirectory)) {return true;}
		
		# Attempt the directory creation and return the result
		return (@mkdir ($newDirectory, 0775, $recursive = true));
	}
	
	
	# Function to get the current folders here
	private function getCurrentFoldersHere ()
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
	private function getCurrentPagesHere ($useDirectory = false, $fullTree = false, $supportedFileTypes = array ('html', 'txt'))
	{
		# Use the current directory if none specified
		if (!$useDirectory) {$useDirectory = $this->currentDirectory;}
		
		# Get the live and staging folders; a check is done first for whether it exists
		$currentFilesLive = array ();
		if (is_dir ($currentDirectoryLive = $this->liveSiteRoot . $useDirectory)) {
			if ($fullTree) {
				$currentFilesLive = directories::flattenedFileListing ($this->liveSiteRoot . $useDirectory, $supportedFileTypes, $includeRoot = false);
				if ($currentFilesLive) {
					$useDirectoryNoSlash = (substr ($useDirectory, -1) == '/' ? substr ($useDirectory, 0, -1) : $useDirectory);
					foreach ($currentFilesLive as $index => $file) {
						$currentFilesLive[$index] = $useDirectoryNoSlash . $file;
					}
				}
			} else {
				$currentFilesLive = directories::listFiles ($this->liveSiteRoot . $useDirectory, $supportedFileTypes, $directoryIsFromRoot = true);
				$currentFilesLive = array_keys ($currentFilesLive);
			}
		}
		
		# Get the current staging files
		$currentFilesStaging = array ();
		foreach ($this->submissions as $submission => $attributes) {
			if ($attributes['directory'] == $useDirectory) {
				$currentFilesStaging[] = $attributes['filename'];
			}
		}
		
		/*
		# Add in the current page (this is only necessary when the page is being aliased)
		#!# This seems to be causing blog page addition breakage, or somewhere else in this function is - says the page already exists when it doesn't
		$currentFilesCurrentPage = array ();
		$currentFilesCurrentPage[] = basename ($this->page);
		*/
		
		# Add in virtual pages, if any; for instance a virtual page of ^/foo/([^/]+)/index.html$ will create index.html as a name when in /foo/bar/
		$currentFilesVirtualPages = array ();
		if ($this->virtualPages) {
			$this->virtualPages = application::ensureArray ($this->virtualPages);
			$delimiter = '@';
			foreach ($this->virtualPages as $virtualPage) {
				if (substr ($virtualPage, -1) == '$') {$virtualPage = substr ($virtualPage, 0, -1);} // Chop off finalisation terminator
				$virtualPageDirname = dirname ($virtualPage) . '/';
				if (preg_match ($delimiter . addcslashes ($virtualPageDirname, $delimiter) . $delimiter, $this->currentDirectory)) {
					$virtualPageBasename = basename ($virtualPage);
					$virtualPageTranslated = $virtualPageBasename;
					$currentFilesVirtualPages[] = $virtualPageTranslated;
				}
			}
		}
		
		# Merge, unique and sort the list
		$currentFiles = array_merge ($currentFilesLive, $currentFilesStaging, /* $currentFilesCurrentPage, */ $currentFilesVirtualPages);
		$currentFiles = array_unique ($currentFiles);
		sort ($currentFiles);
		
		# Return the list
		return $currentFiles;
	}
	
	
	# Function to show a current folder listing
	private function listCurrentResources ($currentResources, $type = 'folders', $actionPersistence = false)
	{
		# Determine a message for there being none
		switch ($type) {
			case 'folders':
				$description = ($this->blogMode ? 'blogs' : 'folders');
				break;
			case 'postings':
				$description = 'blog postings';
				break;
			default:
				$description = $type;
		}
		$noneHtml = "\n<p>There are no {$description} in this area at present.</p>";
		
		# Create a list if any exist
		if (!$currentResources) {return $noneHtml;}
		
		# Add links to each, adding a slash if the resource type is folders
		$currentResourcesLinked = array ();
		foreach ($currentResources as $resource) {
			
			# Add a slash if the resource is the folder type
			if ($type == 'folders') {$resource .= '/';}
			
			# Chop the directory index if blog postings
			if ($type == 'postings') {$resource = $this->chopDirectoryIndex ($resource);}
			
			# Do not list banned locations
			if ($this->matchLocation ($this->bannedLocations, $this->currentDirectory . $resource)) {continue;}
			
			# Add the item, correctly formatted
			#!# Ideally get the title, but this means working out which file (live or staging) to open
			$currentResourcesLinked[] = "<a href=\"{$resource}" . ($actionPersistence ? "?{$this->action}" : '') . "\">{$resource}</a>";
		}
		
		# End if none
		if (!$currentResourcesLinked) {return $noneHtml;}
		
		# Construct the HTML, splitting into a multi-column layout if there are a lot of items
		$html  = "\n<p>The following are the " . (($this->isBlogTreeRoot && $type == 'folders') ? 'blogs' : $type) . ($type == 'postings' ? ' in this blog' : ' which currently exist in this area') . ':</p>';
		if (count ($currentResourcesLinked) <= 40) {
			$html .= application::htmlUl ($currentResourcesLinked);
		} else {
			foreach ($currentResourcesLinked as $index => $item) {
				$currentResourcesLinked[$index] = '<li>' . $item . '</li>';
			}
			$html .= application::splitListItems ($currentResourcesLinked, 3);
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to list current pages (or folders) as a regexp
	private function currentPagesFoldersRegexp ($currentPagesFolders, $allowIndexAliasOverwriting = false)
	{
		# Remove the directory index from the list if it is being aliased and can be 'overwritten'
		if ($allowIndexAliasOverwriting) {
			foreach ($currentPagesFolders as $index => $folder) {
				if ($this->directoryIndex == $folder) {
					unset ($currentPagesFolders[$index]);
				}
			}
		}
		
		# Return the result, returning false if there are none
		return ($currentPagesFolders ? '^(' . implode ('|', $currentPagesFolders) . ')$' : false);
	}
	
	
	# Function to provide a regexp for a proposed new page name is valid
	private function validPageNameRegexp ()
	{
		# Take account of content negotiation semantics for filenames in the regexp
		$regexp = '^(' . ($this->forcePagenameIndex ? 'index' : '[a-z0-9]') . "{1,{$this->maximumFileAndFolderNameLength}}" . ($this->contentNegotiation ? '((\.[-a-z]+)?)\.html' : '\.html') . '|\.menu' . ($this->contentNegotiation ? '((\.[-a-z]+)?)\.html' : '\.html') . '|\.title' . ($this->contentNegotiation ? '((\.[-a-z]+)?)\.txt' : '\.txt') . ')$';
		
		# Return the regexp
		return $regexp;
	}
	
	
	# Function to create a new page
	private function newPage ()
	{
		# Start the HTML
		$html = '';
		
		# If the current section does not exist, require section creation as high up the tree as necessary
		if ($requireSectionCreationHtml = $this->requireSectionCreation ()) {
			$html .= $requireSectionCreationHtml;
			return $html;
		}
		
		# Ensure that the tree is writable, or end
		if (!$this->treesPotentiallyWritable ($this->currentDirectory, $html)) {
			return $html;
		}
		
		# Get the current pages for the live and staging areas
		$currentPages = $this->getCurrentPagesHere ();
		
		# Form for the new page
		$form = new form (array (
			'display' => 'paragraphs',
			'displayDescriptions'	=> true,
			'displayRestrictions'	=> false,
			'formCompleteText'	=> false,
			'submitButtonText'		=> ($this->blogMode ? 'Create new blog posting' : 'Create new page'),
			'submitTo' => "{$this->page}?" . __FUNCTION__,
			'requiredFieldIndicator' => false,
		));
		
		# If there is no directory index, state that this is required
		$forceIndexPageCreation = (!$this->pageIsBeingAliased && !$this->directoryContainsIndex ());
		if ($forceIndexPageCreation) {
			$html = "<p class=\"warning\">This section currently contains no front page ({$this->directoryIndex}). You need to <a href=\"{$this->currentDirectory}{$this->directoryIndex}?edit\">create an index page for this section</a> before creating other pages.</p>";
			return $html;
		}
		
		# Switch between normal and blog mode
		if ($this->blogMode) {
			
			# Widgets
			$form->heading ('', $this->blogModeReminder);
			$form->datetime (array (
				'name' => 'date',
				'title' => 'Date',
				'default' => 'timestamp',
				'editable' => false,
				'required' => true,
			));
			$form->input (array (
				'name' => 'summary',
				'title' => 'Summary',
				'required' => true,
				'maxlength' => 35,
				'size' => 35,
				'description' => 'This will be converted to be part of the URL of the finalised posting. Please enter using standard sentence case.',
			));
			
			# Check for an existing same entry
			if ($unfinalisedData = $form->getUnfinalisedData ()) {
				$proposedLocation = $this->newBlogPostingLocation ($unfinalisedData['date'], $unfinalisedData['summary'], $addIndex = false);
				if ($currentPages = $this->getCurrentPagesHere ($proposedLocation)) {
					$form->registerProblem ('postingexists', "A posting of that name and date <a href=\"{$proposedLocation}\">already exists</a> - please rename, or edit the existing posting if relevant.");
				}
			}
			
		} else {
			
			# Determine if the name should be editable
			$nameIsEditable = (!$forceIndexPageCreation);
			
			# Define a regexp for the current page
			$currentPagesRegexp = $this->currentPagesFoldersRegexp ($currentPages, $allowIndexAliasOverwriting = ($this->allowIndexAliasOverwriting && $forceIndexPageCreation));
			
			# Hack to get the current page submitted, used for a link if necessary
			$pageSubmitted = ((isSet ($_POST['form']) && isSet ($_POST['form']['newpage'])) ? htmlspecialchars ($_POST['form']['newpage'], ENT_COMPAT, $this->charset) : '');
			
			# Heading
			$form->heading ('', '<p class="information"><strong>A page is more detail within an existing section</strong>, not a whole new topic area.' . ($currentPages ? ' <a href="#current">Current pages</a> are listed below.' : '') . '</p><br />');
			
			# Page name
			$description = "
			<ul class=\"comment\">
				<li><strong>All lower-case, a-z and 0-9 only</strong> (no punctuation)</li>
				<li><strong>Must end with .html</strong></li>
				<li><strong>Avoid abbreviations</strong>: make it guessable and self-explanatory</li>
				<!--<li>{$this->maximumFileAndFolderNameLength} characters maximum</li>-->
				<li>If necessary, run two words together</li>
				<li>Consider permanence (e.g. 'contacts' for a telephone number page - it could have addresses later)</li>
			</ul>
			";
			$form->heading (3, 'Choose a short one-word file name, followed by .html');
			$form->input (array (
				'name'			=> 'newpage',
				'title'			=> 'New page address',
				'description'	=> ($nameIsEditable ? $description : false),
				'required'				=> true,
				'regexp'				=> $this->validPageNameRegexp (),
				'default'  => $newPageName = ($nameIsEditable ? $this->attribute : $this->directoryIndex),
				'editable' => $nameIsEditable,
				'disallow' => ($currentPages ? array ($currentPagesRegexp => "Sorry, <a href=\"{$pageSubmitted}\">a page of that name</a> already exists, as shown in the list below. Please try another.") : false),
				'placeholder'	=> 'pagetitle.html',
				'autofocus' => true,
				'prepend' => htmlspecialchars ($this->currentDirectory) . ' ',
			));
		}
		
		# Show the form and get any results
		$result = $form->process ($html);
		
		# Show the folders which currently exist if there are any
		if (!$result) {
			if (!$this->blogMode) {
				$html .= "\n<h2 id=\"current\">Current pages</h2>";
				$html .= $this->listCurrentResources ($currentPages, 'pages');
			}
			return $html;
		}
		
		# Construct the URL for blog mode
		if ($this->blogMode) {
			
			# Get the current blog directory
			$newFile = $this->newBlogPostingLocation ($result['date'], $result['summary']);
#!# Not yet working
			$action = 'edit=blog';
			
		} else {
			
			# Determine the new file location
			$newFile = $this->currentDirectory . $result['newpage'];
			$action = 'edit';
		}
		
		# Show confirmation, but ideally redirect the user directly
		$redirectTo = "{$newFile}?{$action}";
		application::sendHeader (302, $this->editSiteUrl . $redirectTo);
		$html .= "<p class=\"success\">You can now <a href=\"{$redirectTo}\">edit the new " . ($this->blogMode ? 'blog posting' : 'page') . "</a>.</p>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Helper function to require the creation of a section
	private function requireSectionCreation ()
	{
		# Not required if the current directory exists
		if ($this->currentDirectoryExists ()) {return false;}
		
		# Get the nearest section; e.g. a request for /foo/bar/baz.html when only /foo/ exists in this hierarchy would return "/foo/"
		$nearestSection = $this->nearestPage ($this->currentDirectory, true);
		
		# Get the remaining components after the current directory, e.g. /foo/bar/baz.html would result in "bar"
		$delimiter = '@';
		$difference = preg_replace ($delimiter . '^' . addcslashes ($nearestSection, $delimiter) . $delimiter, '', $this->currentDirectory);
		$differenceFolderComponents = explode ('/', $difference);
		$suggestedFolderName = strip_tags ($differenceFolderComponents[0]);
		
		# Compile the HTML
		$html = "<p class=\"warning\">You must <a href=\"{$nearestSection}?section=" . htmlspecialchars (urlencode ($suggestedFolderName)) . "\">create a section " . (count ($differenceFolderComponents) == 1 ? 'here' : 'higher up') . "</a> first, as there is no such section " . htmlspecialchars ($this->currentDirectory) . ' at present.</p>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to assembe a new blog posting location
	private function newBlogPostingLocation ($date, $summary, $addIndex = true)
	{
		# Asembe the pieces
		$currentBlogRoot = $this->getCurrentBlogRoot ();
		$date = str_replace ('-', '/', $date);
		$urlSlug = application::createUrlSlug ($summary);
		$newFile = $currentBlogRoot . $date . '/' . $urlSlug . '/' . ($addIndex ? 'index.html' : '');
		
		# Return the assembled string
		return $newFile;
	}
	
	
	# Function to get the current blog's root
	private function getCurrentBlogRoot ()
	{
		# Do a match
		$delimiter = '@';
		preg_match ($delimiter . '^' . addcslashes ($this->blogMode, $delimiter) . '([^/]+)/' . $delimiter, $this->currentDirectory, $matches);
		
		# Assemble the root
		$currentBlogRoot = (isSet ($matches[1]) ? $this->blogMode . $matches[1] . '/' : NULL);
		
		# Return the root
		return $currentBlogRoot;
	}
	
	
	# Function to show the pages in the current location
	private function showCurrent ()
	{
		# Start the HTML
		$html = '';
		
		# Show the current location
		$html .= "\n<p class=\"information\">You are currently in the location: <strong>{$this->currentDirectory}</strong></p>";
		
		# Switch between normal and blog mode
		if ($this->blogMode && !$this->isBlogTreeRoot) {
			
			# Get the list of postings
			$currentBlogRoot = $this->getCurrentBlogRoot ();
			$postings = $this->getCurrentPagesHere ($currentBlogRoot, $asTree = true, 'html');
			$postings = array_diff ($postings, array ($currentBlogRoot . 'index.html'));
			rsort ($postings);
			$html .= $this->listCurrentResources ($postings, 'postings', true);
			
		# Normal mode
		} else {
			
			# List the current pages
			$html .= "\n<h2>" . ($this->blogMode ? 'Blogs in this section' : 'Sub-sections (folders) in this section') . '</h2>';
			$currentFolders = $this->getCurrentFoldersHere ();
			$html .= $this->listCurrentResources ($currentFolders, 'folders', true);
			$html .= "\n<p>You may wish to <a href=\"?section\">create a new " . ($this->blogMode ? 'blog' : 'section (folder)') . '</a>' . ($currentFolders ? ' if there is not a relevant one already' : '') . '.</p>';
			
			# List the current pages
			$html .= "\n<h2>" . ($this->blogMode ? 'Ancillary pages' : 'Pages') . ' in this section</h2>';
			$currentPages = $this->getCurrentPagesHere ();
			$html .= $this->listCurrentResources ($currentPages, 'pages');
			$html .= "\n<p>You may wish to <a href=\"?newPage\">create a new page</a>" . ($currentPages ? ' if there is not a relevant one already' : '') . '.</p>';
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to list the users
	private function userList ($forceReload = false)
	{
		# Start the HTML
		$html = '';
		
		# Force a reload of the list if necessary
		if ($forceReload) {$this->users = $this->users ($html);}
		
		# Sort the users by username
		$users = $this->users;
		ksort ($users);
		
		# Change the administrator indication
		$usersFormatted = array ();

		foreach ($users as $user => $attributes) {
			$usersFormatted[$user][''] = '';
			if (!isSet ($attributes['Source']) || (isSet ($attributes['Source']) && ($attributes['Source'] != 'Lookup (database)'))) {
				$usersFormatted[$user][''] = "<a href=\"?userAmend={$user}\"><strong>{$user}</strong></a>";
			}
 			$usersFormatted[$user]['Forename'] = htmlspecialchars ($attributes['Forename']);
			$usersFormatted[$user]['Surname'] = htmlspecialchars ($attributes['Surname']);
			$usersFormatted[$user]['E-mail'] = htmlspecialchars ($attributes['E-mail']);
			$usersFormatted[$user]['Administrator'] = ($attributes['Administrator'] ? 'Yes' : 'No');
			if (isSet ($attributes['Source'])) {
				$usersFormatted[$user]['Source'] = $attributes['Source'];
			}
			$usersFormatted[$user]['Actions...']  = "<a href=\"?userAmend={$user}\" title=\"Edit...\"><img src=\"/images/icons/pencil.png\" class=\"icon\" /></a>";
			$usersFormatted[$user]['Actions...'] .= " <a href=\"?userRemove={$user}\" title=\"Delete...\"><img src=\"/images/icons/bin.png\" class=\"icon\" /></a>";
			$usersFormatted[$user]['Actions...'] .= " <a target=\"_blank\" class=\"noarrow\" href=\"http://www.lookup.cam.ac.uk/person/crsid/{$user}\" title=\"Lookup\"><img src=\"/images/icons/help.png\" class=\"icon\" /></a>";
		}
		
		# Compile the HTML of the table of current users
		$html .= "\n<p class=\"information\">The following are currently registered as users of the editing system.<br />" . ($this->lookup ? "To edit a user's details, click on their username, though please note that those users whose details are sourced from a database lookup cannot be edited here but must be edited in the source database instead." : "To edit a user's details, click on their username.") . '</p>';
		$html .= application::htmlTable ($usersFormatted, array (), 'lines', $keyAsFirstColumn = false, false, $allowHtml = true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to add a user
	private function userAdd ($firstRun = false)
	{
		# Start the HTML
		$html = '';
		
		# Create the form itself
		$form = new form (array (
			'displayRestrictions' => false,
			'name' => __FUNCTION__,
			'formCompleteText' => false,
			'submitTo' => "{$this->page}?" . __FUNCTION__,
		));
		
		# Add a heading for the first run
		if ($firstRun) {
			$form->heading ('', '<p class="information">There are currently no users. You are required to create a new administrative user on first login. Please enter your details.</p>');
		}
		
		# Create a list of current users; if the Source field exists, users can only be added if they are a lookup user (so list only local users)
		$users = array ();
		if (isSet ($this->users) && $this->users) {
			foreach ($this->users as $user => $attributes) {
				if (!isSet ($attributes['Source']) || ($attributes['Source'] != 'Lookup (database)')) {
					$users[] = $user;
				}
			}
		}
		
		# Define autocomplete options parameter for the form widget; see: http://jqueryui.com/demos/autocomplete/#remote
		$autocompleteOptions = false;
		if ($this->autocomplete) {
			# JS function to copy the e-mail address, and extract name and split it into forename and surname; see: http://stackoverflow.com/a/12340803
			$focusSelectJsFunction = "
				function( event, ui ) {
					var name = ui.item.label.replace(/^.+\((.+)\)$/g, '$1');
					var forename = name.split(' ').slice(0, -1).join(' ');
					var surname = name.split(' ').slice(-1).join(' ');
					$( '#userAdd_E-mail' ).val( ui.item.value " . ($this->emailDomain ? "+ '@{$this->emailDomain}' " : '') . ");
					$( '#userAdd_Forename' ).val( forename );
					$( '#userAdd_Surname' ).val( surname );
				}
			";
			$autocompleteOptions = array (
				'delay'		=> 0,
				'focus'		=> $focusSelectJsFunction,
				'select'	=> $focusSelectJsFunction,
			);
		}
		
		# Widgets
		$form->input (array (
		    'name'					=> 'Username',
		    'title'					=> "New user's username",
			'description'			=> "Usernames can only have lower-case alphanumeric characters and must be at least {$this->minimumUsernameLength} " . ($this->minimumUsernameLength == 1 ? 'character' : 'characters') . ' in length',
		    'required'				=> true,
		    'size'					=> 10,
		    'maxlength'				=> 10,
			'default'				=> ($firstRun ? $this->user : ''),
			'regexp'				=> "^[a-z0-9]{{$this->minimumUsernameLength},}$",
			'current'				=> $users,
			'autofocus'				=> true,
			'autocomplete'			=> $this->autocomplete,
			'autocompleteOptions'	=> $autocompleteOptions,
		));
		$form->email (array (
		    'name'				=> 'E-mail',
		    'title'				=> 'E-mail address',
		    'required'			=> true,
		));
		$form->input (array (
		    'name'				=> 'Forename',
		    'title'				=> 'Forename',
		    'required'			=> true,
		));
		$form->input (array (
		    'name'				=> 'Surname',
		    'title'				=> 'Surname',
		    'required'			=> true,
		));
		if (!$firstRun) {
			$makeAdministratorText = 'Administrator';
			$form->checkboxes (array (
			    'name'			=> 'Administrator',
			    'values'		=> array ($makeAdministratorText,),
			    'title'			=> 'Grant administrative rights?',
				'description'	=> 'Warning! This will give the right to approve pages, grant new users, etc.',
			));
		}
		$form->textarea ($this->additionalMessageWidget);
		
		# Show the form and get any results or end here
		if (!$result = $form->process ($html)) {
			return $html;
		}
		
		# Flatten the checkbox result
		$result['Administrator'] = ($firstRun ? true : ($result['Administrator'][$makeAdministratorText] ? '1' : '0'));
		
		# Arrange the array into a keyed result
		$newUser[$result['Username']] = $result;
		
		# Insert the data into the CSV file
		if (!csv::addItem ($this->userDatabase, $newUser, $this->databaseTimestampingMode)) {
			return $html;
		}
		
		#!# If making a user an administrator, any existing permissions should be deleted
		
		# Log the change
		$html .= $this->logChange ("Created new user {$result['Username']} with " . ($result['Administrator'] ? 'administrative' : 'editing') . " rights");
		
		# Signal success, firstly reloading the database
		$this->users = $this->users ($html);
		$html .= "\n<p class=\"success\">The user {$result['Forename']} {$result['Surname']} ({$result['Username']}) was successfully added" . ($result['Administrator'] ? ', as an administrator. <a href="/"><strong>Continue.</strong></a>' : ".<br />You may now wish to <a href=\"{$this->page}?permissionGrant={$result['Username']}\"><strong>add permissions for that user</strong></a>.") . '</p>';
		
		# Send mail
		$message  = "You now have access to the website editing facility. You can log into the pureContentEditor system at {$this->editSiteUrl}/ , using your {$this->authTypeName} username and password. You are recommended to bookmark that address in your web browser.";
		$message .= "\n\nYour username is: {$result['Username']}";
		$message .= "\n\nOnce you have logged in, please click on the 'Tips/help' button to see some useful tips.";
		$message .= "\n\n" . ($result['Administrator'] ? 'You have been granted administrative rights, so you have editable access across the site rather than access to particular areas. You can also create/administer users and permissions.' : 'You will be separately advised of the area(s) of the site which you have permission to alter.');
		$message .= ($result['message'] ? "\n\n{$result['message']}" : '');
		$html .= $this->sendMail ($result['Username'], $message, $subjectSuffix = 'you now have access');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to amend a user's details
	private function userAmend ()
	{
		# Start the HTML
		$html = '';
		
		# Get the username (if supplied)
		$username = $this->attribute;
		
		# If a user has been selected but does not exist, say so
		if ($username && !isSet ($this->users[$username])) {
			$html .= "\n<p class=\"failure\">There is no user " . htmlspecialchars ($this->attribute) . '.</p>';
		}
		
		# Ensure the user is a local user, as looked-up users cannot be edited
		if (isSet ($this->users[$username]['Source']) && $this->users[$username]['Source'] == 'Lookup (database)') {
			$html .= "\n<p class=\"failure\">This user's details cannot be edited as their information comes from an external database lookup.</p>";
			return $html;
		}
		
		# Show the list of users with the links if no user has been selected
		if (!$username || !isSet ($this->users[$username])) {
			$html .= $this->userList ();
			return $html;
		}
		
		# Create the form itself
		$form = new form (array (
			'displayRestrictions' => false,
			'name' => __FUNCTION__,
			'formCompleteText' => false,
			'submitTo' => "{$this->page}?" . __FUNCTION__ . "={$username}",
		));
		
		# Form widgets
		$form->select (array (
		    'name'		=> 'Username',
			'values'	=> array ($username),
		    'title'		=> "Existing user's username",
			'default'	=> $username,
			'required'	=> true,
			'editable'	=> false,
		));
		$form->email (array (
		    'name'            => 'E-mail',
		    'title'                    => 'E-mail address',
		    'required'                => true,
			'default' => $this->users[$username]['E-mail'],
		));
		$form->input (array (
		    'name'            => 'Forename',
		    'title'                    => 'Forename',
		    'required'                => true,
			'default' => $this->users[$username]['Forename'],
		));
		$form->input (array (
		    'name'            => 'Surname',
		    'title'                    => 'Surname',
		    'required'                => true,
			'default' => $this->users[$username]['Surname'],
		));
		
		# If the current user selected themselves as a user, do not allow them to demote themselves
		$makeAdministratorText = 'Administrator';
		$form->checkboxes (array (
		    'name'            => 'Administrator',
		    'values'            => array ($makeAdministratorText,),
		    'title'                    => 'Grant administrative rights?',
			#!# Review this restriction
			'description' =>  ($this->user == $username ? 'Note that administrators cannot demote themselves but must get another administrator to do this.' : 'Warning! This will give the right to approve pages, grant new users, etc.'),
			'default' => ($this->users[$username]['Administrator'] ? $makeAdministratorText : ''),
			'editable' => ($this->user != $username),
		));
		
		# Additional message
		$form->textarea ($this->additionalMessageWidget);
		
		# Show the form and get any results or end here
		if (!$result = $form->process ($html)) {
			return $html;
		}
		
		# Flatten the checkbox result
		$result['Administrator'] = (($this->user == $username) ? true : ($result['Administrator'][$makeAdministratorText] ? '1' : '0'));
		
		# Arrange the array into a keyed result
		$user[$username] = $result;
		
		# Replace the data in the CSV file (add performs replacement when the key already exists)
		if (!csv::addItem ($this->userDatabase, $user, $this->databaseTimestampingMode)) {
			return $html;
		}
		
		# Signal success
		$html .= "\n<p class=\"success\">The user {$result['Username']}'s details have been successfully updated.</p>";
		
		# Log the change
		$html .= $this->logChange ("Amended user details for {$result['Username']} with " . ($result['Administrator'] ? 'administrative' : 'editing') . " rights");
		
		# Flag changes of administrative status, reloading the database at this point
		if ($this->users[$username]['Administrator'] != $result['Administrator']) {
			$html .= "\n<p class=\"success\">The user " . ($result['Administrator'] ? 'now' : 'no longer') . ' has administrative rights.</p>';
			$message = ($result['Administrator'] ? 'You have been granted administrative rights, so you have editable access across the site rather than access to particular areas. You can also create/administer users and permissions.' : 'Your administrator-level permission for the editing system has now been ended, so you are now an ordinary user. Thank you for your help with administering the website.') . ($result['message'] ? "\n\n{$result['message']}" : '');
			$this->users = $this->users ($html);
			$html .= $this->sendMail ($username, $message, $subjectSuffix = 'change of administrator rights');
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to remove a user; there will always be one administrator remaining (the current user) as administrative privileges are required to use this function
	private function userRemove ()
	{
		# Start the HTML
		$html = '';
		
		# Create a list of users with unapproved submissions
		$this->usersWithUnapprovedSubmissions = $this->usersWithUnapprovedSubmissions ();
		
		# Get the list of users
		$deletableUsers = $this->userSelectionList ($excludeUsersWithUnapprovedSubmissions = true, $excludeCurrentUser = true, false, $excludeLookupUsers = true);
		
		# Prevent the form display if there are no users
		if (!$deletableUsers) {
			$html .= $message = "\n" . '<p class="information">' . ($this->usersWithUnapprovedSubmissions ? 'There remain' : 'There are') . ' no users available for deletion.</p>';
			$html .= ($this->usersWithUnapprovedSubmissions ? "<p class=\"warning\">(Users having <a href=\"{$this->page}?review\">submissions awaiting approval</a> (which must be approved/deleted first) cannot be deleted.)</p>" : '');
			return $html;
		}
		
		# Create the form itself
		$form = new form (array (
			'displayRestrictions' => false,
			'name' => __FUNCTION__,
			'formCompleteText' => false,
			'submitTo' => "{$this->page}?" . __FUNCTION__,
		));
		
		# Determine whether there are approvals outstanding
		if ($this->usersWithUnapprovedSubmissions) {
			$form->heading ('', "<p class=\"warning\">Note: some users are not listed as they have <a href=\"{$this->page}?review\">submissions awaiting approval</a>, which must be approved/deleted first.</p>");
		}
		
		# Widgets
		$form->heading ('', "<p>Note: deleting a user will also revoke any permissions they have.</p>");
		$form->select (array (
		    'name'            => 'username',
		    'values'            => $deletableUsers,
		    'title'                    => 'User to delete',
		    'required'        => 1,
			'get' => __FUNCTION__,
			'description'	=> ($this->usersWithUnapprovedSubmissions ? "If the user you are wanting to delete is not listed, it is because they have <a href=\"{$this->page}?review\">submissions awaiting approval</a>." : ''),
		));
		$form->input (array (
		    'name'            => 'confirmation',
		    'title'                    => 'Confirm username',
			'description'	=> 'Please type in the username for confirmation, to prevent accidental deletions',
		    'required'                => true,
		));
		$form->textarea ($this->additionalMessageWidget);
		
		# Ensure username matching
		$form->validation ('same', array ('username', 'confirmation'));
		
		# Show the form and get any results or end here
		if (!$result = $form->process ($html)) {
			return $html;
		}
		
		# Create a list of the user's permissions
		$permissions = array ();
		foreach ($this->permissions as $key => $attributes) {
			if ($attributes['Username'] == $result['username']) {
				if (!isSet ($attributes['Source']) || (isSet ($attributes['Source']) && ($attributes['Source'] != 'Lookup (database)'))) {
					$permissions[] = $key;
				}
			}
		}
		
		# Delete the permissions if there are any
		if ($permissions) {
			if (!csv::deleteData ($this->permissionsDatabase, $permissions, $this->databaseTimestampingMode)) {
				$html .= $this->reportErrors ('Unfortunately, the operation failed - there was a problem deleting their permissions; the attempt to delete user themselves has therefore been cancelled.');
				return $html;
			}
		}
		
		# Log the change
		$html .= $this->logChange ("Deleted all permissions for user {$result['username']}");
		
		# Delete the user
		if (!csv::deleteData ($this->userDatabase, $result['username'], $this->databaseTimestampingMode)) {
			$html .= $this->reportErrors ('Unfortunately, the operation failed - there was a problem deleting the user, although any permissions were deleted successfully.');
			return $html;
		}
		
		# Log the change
		$html .= $this->logChange ("Deleted user {$result['username']}");
		
		# Signal success then show the new list of users
		$html .= "\n<p class=\"success\">The user {$result['username']}" . ($permissions ? ' and their permissions were' : ' was') . " successfully deleted.</p>";
		$html .= $this->sendMail ($result['username'], 'Your access to the editing system has now been ended. Thank you for your help with the website.' . ($result['message'] ? "\n\n{$result['message']}" : ''), $subjectSuffix = 'access ended');
		
		# Show the user list
		$html .= $this->userList (true);
		
		# Return the HTML
		return $html;
	}
	
	# Function to get the number of users with unapproved submissions
	private function usersWithUnapprovedSubmissions ()
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
	private function userSelectionList ($excludeUsersWithUnapprovedSubmissions = false, $excludeCurrentUser = false, $excludeAdministrators = false, $excludeLookupUsers = false)
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
			
			# Skip looked-up users if necessary
			if ($excludeLookupUsers && isSet ($attributes['Source']) && ($attributes['Source'] == 'Lookup (database)')) {continue;}
			
			# Add the user to the list
			$users[$user] = "$user: {$attributes['Forename']} {$attributes['Surname']}" . ($attributes['Administrator'] ? ' (Administrator)' : '');
		}
		
		# Sort the userlist
		ksort ($users);
		
		# Return the userlist
		return $users;
	}
	
	
	# Function to create an administrator userlist
	private function administratorSelectionList ($enableNoneOption = false, $excludeCurrentUser = true)
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
	private function scopeSelectionList ($excludeLookupUsers = false)
	{
		# Compile the permissions list
		$permissions = array ();
		if ($this->permissions) {
			foreach ($this->permissions as $key => $attributes) {
				if ($excludeLookupUsers && isSet ($attributes['Source']) && ($attributes['Source'] == 'Lookup (database)')) {continue;}
				$name = '';
				if (isSet ($this->users[$attributes['Username']])) {
					$userAttributes = $this->users[$attributes['Username']];
					$name = " ({$userAttributes['Forename']} {$userAttributes['Surname']})";
				}
				$permissions[$key] = "{$attributes['Username']}{$name}: " . $attributes['Location'];
			}
		}
		
		# Return the userlist
		return $permissions;
	}
	
	
	# Function to list the permissions
	private function permissionList ()
	{
		# Start the HTML
		$html = '';
		
		# If there are no permissions assigned, say so
		if (!$this->permissions) {
			$html .= "\n<p class=\"information\">There are no permissions assigned (other than universal permissions available to administrators). You may wish to <a href=\"{$this->page}?permissionGrant\">grant some permissions</a>.</p>";
			return $html;
		}
		
		# Get the permissions
		$permissions = $this->permissions;
		
		# Start a table of data; NB This way is better in this instance than using htmlTable (), as the data contains HTML which will have entity conversion applied;
		$html .= "\n<p class=\"information\">The list below shows the permissions which are currently assigned.<br />" . ($this->lookup ? "To edit a permission, click on link in the left-most column, though please note that those users whose permissions are sourced from a database lookup cannot be edited here but must be edited in the source database instead." : '') . '</p>';
		$html .= "\n" . '<table class="lines">';
		$html .= "\n\t" . '<tr>';
		#!# This line is only added because dateLimitation is the only amendable item currently
		if (!$this->disableDateLimitation) {$html .= "\n\t\t" . '<th>Amend?</th>';}
		$html .= "\n\t\t" . '<th>User:</th>';
		$html .= "\n\t\t" . '<th>Can make changes to:</th>';
		$html .= "\n\t\t" . '<th>Can make pages live directly?</th>';
		if (!$this->disableDateLimitation) {$html .= "\n\t\t" . '<th>Date limitation?</th>';}
		$html .= "\n\t\t" . '<th>Actions..</th>';
		
		#!# Clumsy; refactor this whole section to organise the data first then just application::htmlTable() it
		foreach ($permissions as $permission => $attributes) {
			if (isSet ($attributes['Source'])) {$html .= "\n\t\t" . '<th>Source</th>';}
			break;
		}
		$html .= "\n\t" . '</tr>';
		
		# Loop through each file to create the table
		foreach ($permissions as $permission => $attributes) {
			
			# Create a table row
			$html .= "\n\t" . '<tr>';
			#!# This line is only added because dateLimitation is the only amendable item currently
			if (!$this->disableDateLimitation) {$html .= "\n\t\t" . '<td>' . (!isSet ($attributes['Source']) || (isSet ($attributes['Source']) && ($attributes['Source'] != 'Lookup (database)')) ? "<a href=\"?permissionAmend=$permission\">" . ($this->action == 'permissionAmend' ? '<strong>[Amend]</strong>' : '[Amend]') . '</a>' : '') . '</td>';}
			$html .= "\n\t\t" . '<td>' . $this->convertUsername ($attributes['Username']) . '</td>';
			$html .= "\n\t\t" . '<td>' . $this->convertPermission ($attributes['Location'], $descriptions = false) . '</td>';
			$html .= "\n\t\t" . '<td>' . ($this->userIsAdministrator ($attributes['Username']) ? 'Yes (administrator)' : ($attributes['Self-approval'] ? 'Yes': 'No')) . '</td>';
			if (!$this->disableDateLimitation) {$html .= "\n\t\t" . '<td>' . $this->formatDateLimitation ($attributes['Startdate'], $attributes['Enddate']) . '</td>';}
			if (isSet ($attributes['Source'])) {$html .= "\n\t\t" . '<td>' . $attributes['Source'] . '</td>';}
			$html .= "\n\t\t<td>" . "<a href=\"?permissionAmend={$permission}\" title=\"Edit...\"><img src=\"/images/icons/pencil.png\" class=\"icon\" /></a>" . " <a href=\"?permissionRevoke={$permission}\" title=\"Delete...\"><img src=\"/images/icons/bin.png\" class=\"icon\" /></a> <a target=\"_blank\" class=\"noarrow\" href=\"http://www.lookup.cam.ac.uk/person/crsid/{$attributes['Username']}\" title=\"Lookup\"><img src=\"/images/icons/help.png\" class=\"icon\" /></a></td>";
			$html .= "\n\t" . '</tr>';
		}
		$html .= "\n" . '</table>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to chop the directory index off a location
	private function chopDirectoryIndex ($location)
	{
		# Return the value
		$delimiter = '|';
		$location = preg_replace ($delimiter . '/' . addcslashes ($this->directoryIndex, $delimiter) . '$' . $delimiter, '/', $location);
		return $location;
	}
	
	
	# Function to grant permission to a user
	private function permissionGrant ($user = false)
	{
		# Start the HTML
		$html = '';
		
		# Determine the available users to which permissions are available to be granted (i.e. all except administrators)
		$users = $this->userSelectionList (false, false, $excludeAdministrators = true);
		
		# If there are no users available, say so
		if (!$users) {
			$html .= "\n<p class=\"information\">There are no non-administrative users, so no permissions can be granted. You may wish to <a href=\"{$this->page}?userAdd\">add a user</a>.</p>";
			return $html;
		}
		
		# If a user is selected, but that user does not exist, say so with a non-fatal warning
		if ($user && !isSet ($users[$user])) {
			$html .= "\n<p class=\"failure\">There is no non-administrator user " . htmlspecialchars ($user) . '. Please select a valid user from the list below.</p>';
		}
		
		# Determine the scopes, the last being the default
		$scopes = array (
			$this->page => 'This page only',
			$this->currentDirectory => 'Pages in this section',
			$this->currentDirectory . '*' => 'Pages in this section and any subsections',
		);
		
		# Compile the scopes list and the last in the list
		foreach ($scopes as $scope => $description) {
			$scopeList[$scope] = "{$description} - {$scope}";
		}
		$defaultScope = $this->currentDirectory . '*';
		
		# Create the form itself
		$form = new form (array (
			'displayRestrictions' => false,
			'name' => __FUNCTION__,
			'formCompleteText' => false,
			'displayDescriptions' => false,
			'submitTo' => "{$this->page}?" . __FUNCTION__,
			'nullText' => 'Please select',
		));
		
		# Do not include administrators, as they do not need permissions
		$form->select (array (
		    'name'            => 'username',
		    'values'            => $users,
		    'title'                    => 'Allow user',
		    'required'        => 1,
			'default' => (($user && isSet ($users[$user])) ? $user : ''),
		));
		$form->select (array (
		    'name'            => 'scope',
		    'values'            => $scopeList,
		    'title'                    => 'Allow changes to',
		    'required'        => 1,
		    'default'        => $defaultScope,
		));
		$selfApprovalText = 'User can make pages live directly';
		$form->checkboxes (array (
		    'name'		=> 'Self-approval',
		    'values'	=> array ($selfApprovalText ,),
		    'title'		=> 'Allow user to make pages live directly',
		));
		if (!$this->disableDateLimitation) {
			$form->datetime (array (
			    'name'            => 'Startdate',
			    'title'                    => 'Optional availability start date',
			    'level'                    => 'date',
				'prefill' => true,
			));
			$form->datetime (array (
			    'name'            => 'Enddate',
			    'title'                    => 'Optional availability end date',
			    'level'                    => 'date',
			));
			$this->checkStartEndDate ($form);
		}
		$form->textarea ($this->additionalMessageWidget);
		
		# Check the key is not already in the database
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			$key = "{$unfinalisedData['username']}:{$unfinalisedData['scope']}";
			if (isSet ($this->permissions[$key])) {
				$form->registerProblem ('permissionexists', "The permission for user <em>{$unfinalisedData['username']}</em> to amend <em>{$unfinalisedData['scope']}</em> already exists.");
			}
		}
		
		# Show the form and get any results or end here
		if (!$result = $form->process ($html)) {
			return $html;
		}
		
		#!# Check needed if an encompassing permission higher up already exists
		
		# Arrange the array
		$newPermission[$key] = array (
			'Key' => $key,
			'Username' => $result['username'],
			'Location' => $result['scope'],
			'Startdate' => ($this->disableDateLimitation ? '' : $result['Startdate']),
			'Enddate' => ($this->disableDateLimitation ? '' : $result['Enddate']),
			'Self-approval' => $result['Self-approval'][$selfApprovalText],
		);
		
		# Insert the data into the CSV file
		if (!csv::addItem ($this->permissionsDatabase, $newPermission, $this->databaseTimestampingMode)) {
			#!# Inform admin
			$html .= "\n<p class=\"failure\">There was a problem adding the permission.</p>";
			return $html;
		}
		
		# Log the change
		$html .= $this->logChange ("Granted user {$result['username']} permission to edit {$result['scope']} " . ($this->disableDateLimitation ? '' : ($result['Startdate'] ? "from {$result['Startdate']} to {$result['Enddate']}" : 'no time limitation')) . ($result['Startdate'] && $result['Self-approval'][$selfApprovalText] ? ' with ' : '') . ($result['Self-approval'][$selfApprovalText] ? 'self-approval allowed' : 'self-approval not allowed'));
		
		# Construct a time limitation notice
		$timeLimitationMessage = ($this->disableDateLimitation ? '' : ($result['Startdate'] ? "\n\nYou can make changes between: " . $this->convertTimestamp ($result['Startdate'], $includeTime = false) . ' and ' . $this->convertTimestamp ($result['Enddate'], $includeTime = false) . ' inclusive.' : ''));
		
		# Signal success
		$html .= "\n<p class=\"success\">The permission {$result['scope']} for the user {$result['username']} was successfully added.</p>";
		$directLink = $this->editSiteUrl . ((substr ($result['scope'], -1) == '*') ? substr ($result['scope'], 0, -1) : $result['scope']);
		
		# Send the e-mail
		$html .= $this->sendMail ($result['username'], "You have been granted permission to make changes to " . $this->convertPermission ($result['scope'], $descriptions = true, $addLinks = false, $lowercaseStart = true) . ".\n\nThe direct link for this in the editing system is:\n{$directLink}\n\nThis means that when you are in that area of the website while using the editor system, you will see an additional button marked 'edit this page' when editing is allowed.". $timeLimitationMessage . ($result['message'] ? "\n\n{$result['message']}" : ''), $subjectSuffix = 'new area you can edit');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to check the start and end date
	private function checkStartEndDate (&$form)
	{
		# Ensure both are completed if one is
		$form->validation ('all', array ('Startdate', 'Enddate'));
		
		# Take the unfinalised data to deal with start/end date comparisons
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['Startdate'] && $unfinalisedData['Enddate']) {
				
				# Assemble the start & end dates as a number (this would normally be done in ultimateForm in the post-unfinalised data processing section
				$startDate = (int) str_replace ('-', '', $unfinalisedData['Startdate']);
				$endDate = (int) str_replace ('-', '', $unfinalisedData['Enddate']);
				
				# Check that the start (and thereby the end date) are after the current date
				if ($startDate < date ('Ymd')) {
					$form->registerProblem ('datefuture', 'The start/end dates cannot be retrospective. Please go back and correct this.');
				} else {
					
					# Check that the start date comes before the end date; NB the >= seems to work successfully with comparison of strings including the dash (-) character
					if ($startDate >= $endDate) {
						$form->registerProblem ('datemismatch', 'The end date must be after the start date. Please go back and correct this.');
					}
				}
			}
		}
		
		# No return value as the form is passed as a handle
		return;
	}
	
	
	# Function to amend an existing permission
	private function permissionAmend ()
	{
		# Start the HTML
		$html = '';
		
		# Get the permission (if supplied)
		$permission = $this->attribute;
		
		# If a permission has been selected but does not exist, say so
		if ($permission && !isSet ($this->permissions[$permission])) {
			$html .= "\n<p class=\"failure\">There is no permission " . htmlspecialchars ($permission) . '.</p>';
			return $html;
		}
		
		# If the user is an administrator already, deny editability
		if ($permission && $this->userIsAdministrator ($this->permissions[$permission]['Username'])) {
			$html .= "\n<p class=\"failure\">The user concerned is already an administrator so this existing (and anomalous) permission can only be <a href=\"{$this->page}?permissionRevoke={$permission}\">revoked</a>.</p>";
			return $html;
		}
		
		# Ensure the user is a local user, as looked-up users cannot be edited
		if (isSet ($this->permissions[$permission]['Source']) && $this->permissions[$permission]['Source'] == 'Lookup (database)') {
			$html .= "\n<p class=\"failure\">This permission cannot be edited as its details come from an external database lookup.</p>";
			return $html;
		}
		
		# Show the list of users with the links if no permission has been selected
		if (!$permission || !isSet ($this->permissions[$permission])) {
			$html .= $this->permissionList ();
			return $html;
		}
		
		# Create the form itself
		$form = new form (array (
			'displayRestrictions' => false,
			'name' => __FUNCTION__,
			'formCompleteText' => false,
			'submitTo' => "{$this->page}?" . __FUNCTION__ . "=$permission",
		));
		
		# Extract the username and scope from the permission
		list ($username, $scope) = explode (':', $permission, 2);
		
		# Form widgets
		$form->select (array (
		    'name'		=> 'Permission',
			'values'	=> array ($permission => "User <em>{$username}</em> can edit <em>{$scope}</em>"),
		    'title'		=> "Existing permission",
			'default'	=> $permission,
			'required'	=> true,
			'editable'	=> false,
			'entities'	=> false,
		));
		$selfApprovalText = 'User can make pages live directly';
		$form->checkboxes (array (
		    'name'            => 'Self-approval',
		    'values'            => array ($selfApprovalText, ),
		    'title'                    => 'Allow user to make pages live directly',
			'default'            => ($this->permissions[$permission]['Self-approval'] ? $selfApprovalText : ''),
		));
		if (!$this->disableDateLimitation) {
			$form->datetime (array (
			    'name'            => 'Startdate',
			    'title'                    => 'Optional availability start date',
			    'level'                    => 'date',
				'default'            => $this->permissions[$permission]['Startdate'],
			));
			$form->datetime (array (
			    'name'            => 'Enddate',
			    'title'                    => 'Optional availability end date',
			    'level'                    => 'date',
				'default'            => $this->permissions[$permission]['Enddate'],
			));
			$this->checkStartEndDate ($form);
		}
		$form->textarea ($this->additionalMessageWidget);
		
		# Show the form and get any results or end here
		if (!$result = $form->process ($html)) {
			return $html;
		}
		
		# Arrange the array into a keyed result
		list ($result['Username'], $result['Location']) = explode (':', $result['Permission']);
		$amendedPermission[$permission] = array (
			'Key' => $permission,
			'Username' => $result['Username'],
			'Location' => $result['Location'],
			'Self-approval' => $result['Self-approval'][$selfApprovalText],
			'Startdate' => ($this->disableDateLimitation ? '' : $result['Startdate']),
			'Enddate' => ($this->disableDateLimitation ? '' : $result['Enddate']),
		);
		
		# Replace the data in the CSV file (add performs replacement when the key already exists)
		if (!csv::addItem ($this->permissionsDatabase, $amendedPermission, $this->databaseTimestampingMode)) {
			#!# Inform admin
			$html .= "\n<p class=\"failure\">There was a problem updating the permission.</p>";
			return $html;
		}
		
		# Cache the original permission then reload the database
		$originalPermission = $this->permissions[$permission];
		$this->permissions = $this->permissions ();
		$amendedPermission = $this->permissions[$permission];
		
		# Flag changes of administrative status
		if (($originalPermission === $amendedPermission)) {
			$html .= "\n<p class=\"information\">No changes have been made to the permission for the user <em>{$result['Username']}</em> to edit <em>{$result['Location']}</em>, so no action was taken.</p>";
		} else {
			
			# Determine what has changed
			if ($dateHasChanged = ($originalPermission['Startdate'] . $originalPermission['Enddate'] != $amendedPermission['Startdate'] . $amendedPermission['Enddate'])) {
				$dateNowEmpty = ($amendedPermission['Startdate'] . $amendedPermission['Enddate'] == '');
			}
			$selfApprovalHasChanged = ($originalPermission['Self-approval'] != $amendedPermission['Self-approval']);
			
			# Log the change
			$html .= $this->logChange ("Amended permission details for {$permission} " . ($dateHasChanged ? (!$dateNowEmpty ? "now time-limited from {$result['Startdate']} to {$result['Enddate']}" : 'now no time limitation') : '') . ($dateHasChanged && $selfApprovalHasChanged ? ' and ' : '') . ($selfApprovalHasChanged ? ($amendedPermission['Self-approval'] ? 'self-approval now allowed' : 'self-approval no longer allowed') : ''));
			
			# Show an on-screen message
			$html .= "\n<p class=\"success\">Changes have been made to the permission for {$result['Username']} to change {$result['Location']}.</p>";
			
			# Construct the e-mail message and send it
			$message =
				"Your permission to change {$result['Location']} has been amended and is now as follows:"
				. ($this->disableDateLimitation ? '' : ($dateHasChanged ? "\n\n- " . (!$dateNowEmpty ? "You can now make changes from: " . $this->convertTimestamp ($result['Startdate'], $includeTime = false) . ' until ' . $this->convertTimestamp ($result['Enddate'], $includeTime = false) . '.' : 'You no longer have limitations on when you can make changes.') : ''))
				. ($selfApprovalHasChanged ? "\n\n- " . ($amendedPermission['Self-approval'] ? 'You can now choose to make pages live directly.' : 'The option you had of making pages live directly has been ended - pages require administrator approval.') : '')
				. ($result['message'] ? "\n\n{$result['message']}" : '');
			$html .= $this->sendMail ($username, $message, $subjectSuffix = 'change to permission');
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to list the current user's permissions
	private function myAreas ()
	{
		# Start the HTML
		$html = '';
		
		# If the user is an administrator, state that they have universal permission
		if ($this->userIsAdministrator) {
			$html .= "\n<p class=\"success\">As you are an administrator, you have editable access across the site rather than access to particular areas.</p>";
			return $html;
		}
		
		# If no permissions, say so
		if (!$this->currentUserPermissions) {
			$html .= "\n<p>Although you have access to this facility as a whole, you do not currently have permission to edit any areas of the site.</p>";
			return $html;
		}
		
		# Convert the permissions to a human-readable form
		$currentUserPermissions = $this->convertPermissionsList ($this->currentUserPermissions);
		
		# Compile the HTML
		$html .= "\n<p>You have permission to make changes to the following at present:</p>" . application::htmlUl ($currentUserPermissions);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get the current user's permissions; note this does not deal with the special case of administrators
	private function currentUserPermissions ()
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
	private function convertPermissionsList ($permissions, $keysOnly = false)
	{
		# Loop through the permissions
		$readablePermissions = array ();
		foreach ($permissions as $permission) {
			$location = $this->permissions[$permission]['Location'];
			$readablePermissions[$location] = $this->convertPermission ($location);
		}
		
		# If only array keys are required, return that
		if ($keysOnly) {
			$readablePermissions = array_keys ($readablePermissions);
		}
		
		# Return the list
		return $readablePermissions;
	}
	
	
	# Function to convert a single permission
	private function convertPermission ($location, $descriptions = true, $addLinks = true, $lowercaseStart = false)
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
		$string = $startingString;
		if (!$lowercaseStart) {$string = ucfirst ($startingString);}
		if ($descriptions) {
			$string .= ' ' . ($addLinks ? "<a href=\"$location\">" : '') . "{$location}{$sectionTitleHtml}" . ($addLinks ? '</a>' : '') . $endingString;
		} else {
			$string  = ($addLinks ? "<a href=\"{$location}\" title=\"" . $string . " {$location}{$endingString}\">" : '') . "{$location}{$star}{$sectionTitleHtml}" . ($addLinks ? '</a>' : '');
		}
		
		# Return the constructed string
		return $string;
	}
	
	
	# Function to reformat a date limitation
	private function formatDateLimitation ($start, $end)
	{
		# If no start and end, return an empty string
		if (!$start && !$end) {return '<span class="comment">-</span>';}
		
		# Otherwise construct the string
		return $this->formatSqlDate ($start) .  ' to<br />' . $this->formatSqlDate ($end);
	}
	
	
	# Function to reformat a date in SQL format
	private function formatSqlDate ($date)
	{
		# Attempt to split out the year, month and date
		if (!list ($year, $month, $day) = explode ('-', $date)) {return $date;}
		
		# Else return the full date, with the date and month formatted sensibly
		$months = array (1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',);
		return (int) $day . '/' . $months[(int) $month] . "/{$year}";
	}
	
	
	# Function to get contents of the title file for a section
	private function getSectionTitle ($location)
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
	private function makeTimestamp ()
	{
		# Return the timestamp
		return date ('Ymd-His');
	}
	
	
	# Function to convert a timestamp to a string usable by strtotime
	private function convertTimestamp ($timestamp, $includeTime = true, $asUnixtime = false)
	{
		# Convert the timestamp
		$timestamp = preg_replace ('/-(\d{2})(\d{2})(\d{2})$/D', ' $1:$2:$3', $timestamp);
		
		# Determine the output string to use
		$format = 'l jS M Y' . ($includeTime ? ', g.ia' : '');	// Previously: ($includeTime ? 'g.ia \o\n ' : '') . 'jS M Y';
		
		# Convert to Unixtime
		$unixtime = strtotime ($timestamp);
		
		# Return as Unixtime if required
		if ($asUnixtime) {return $unixtime;}
		
		# Convert the timestamp
		$string = date ($format, $unixtime);
		
		# Return the string
		return $string;
	}
	
	
	# Function to remove a permission
	private function permissionRevoke ()
	{
		# Start the HTML
		$html = '';
		
		# Get the permissions from the CSV file
		$permissions = $this->scopeSelectionList ($excludeLookupUsers = true);
		
		# If there are no permissions assigned, say so
		if (!$permissions) {
			$html .= "\n<p class=\"information\">There are no permissions assigned (other than universal permissions available to administrators). You may wish to <a href=\"{$this->page}?permissionGrant\">grant some permissions</a>.</p>";
			return $html;
		}
		
		# Get the permission (if supplied)
		$permission = $this->attribute;
		
		# If a permission has been selected but does not exist, say so
		if ($permission && !isSet ($permissions[$permission])) {
			$html .= "\n<p class=\"failure\">There is no permission " . htmlspecialchars ($permission) . '.</p>';
			return $html;
		}
		
		# Ensure the user is a local user, as looked-up users cannot be edited
		if (isSet ($permissions[$permission]['Source']) && $permissions[$permission]['Source'] == 'Lookup (database)') {
			$html .= "\n<p class=\"failure\">This permission cannot be edited as its details come from an external database lookup.</p>";
			return $html;
		}
		
		# Create the form itself
		$form = new form (array (
			'displayRestrictions' => false,
			'name' => __FUNCTION__,
			'formCompleteText' => false,
			'submitTo' => "{$this->page}?" . __FUNCTION__,
			'submitButtonText' => 'Delete',
		));
		
		# Widgets
		$form->select (array (
		    'name'            => 'key',
		    'values'            => $permissions,
		    'title'                    => 'Permission to delete',
			'description'	=> 'Permissions are listed in the form of <em>username (actual name): area</em>',
		    'required'        => 1,
			'default'	=> $permission,
			'multiple' => false,
			'editable' => (!$permission),
		));
		$form->input (array (
		    'name'            => 'username',
		    'title'                    => 'Confirm username',
			'description'	=> 'Please type in the username (as shown in the selection above) for confirmation, to prevent accidental deletions',
		    'required'                => true,
		));
		$form->textarea ($this->additionalMessageWidget);
		
		# Check the username matches; note that a >validation can't be used as the 'key' field is in a special format that requires the username to be extracted first
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['key']) {
				list ($username, $scope) = explode (':', $unfinalisedData['key'], 2);
				if (($unfinalisedData['username']) && ($username != $unfinalisedData['username'])) {
					$form->registerProblem ('mismatch', 'The usernames you entered did not match.');
				}
			}
		}
		
		# Show the form and get any results or end here
		if (!$result = $form->process ($html)) {
			return $html;
		}
		
		# Delete the entry
		if (!csv::deleteData ($this->permissionsDatabase, $result['key'], $this->databaseTimestampingMode)) {
			$html .= "\n<p class=\"failure\">There was a problem revoking the permission.</p>";
			return $html;
		}
		
		# Signal success
		$html .= "\n<p class=\"success\">The permission {$scope} for the user " . $this->convertUsername ($result['username']) . ' was successfully deleted.</p>';
		
		# Log the change
		$html .= $this->logChange ("Revoked user {$result['username']}'s permission to edit {$scope}");
		
		# Send an e-mail (but don't reload the database!)
		$html .= $this->sendMail ($result['username'], "Your permission to make changes to " . $this->convertPermission ($scope, $descriptions = true, $addLinks = false, $lowercaseStart = true) . ' has now been ended. Thank you for your help with this section.' . ($this->userIsAdministrator ($result['username']) ? ' However, you remain an administrator so have editable access across the site.' : '') . ($result['message'] ? "\n\n{$result['message']}" : ''), $subjectSuffix = 'removal of editing rights for an area');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine submissions in the same location
	private function moreSubmissionsInSameLocation ($currentSubmission, $earlierFilesOnly = true, $excludeCurrent = true, $organiseByUser = true)
	{
		# Get the current file location
		$fileLocation = $this->submissions[$currentSubmission]['directory'] . $this->submissions[$currentSubmission]['filename'];
		
		# Get the timestamp of current submission
		$currentSubmissionTimestamp = $this->stringToNumericTimestamp ($this->submissions[$currentSubmission]['timestamp']);
		
		# Find files in the same location
		$total = 0;
		$moreSubmissionsInSameLocation = array ();
		foreach ($this->submissions as $submission => $attributes) {
			
			# Skip current file, if required
			if ($excludeCurrent && ($submission == $currentSubmission)) {continue;}
			
			# Exclude later files if necessary
			if ($earlierFilesOnly) {
				$timestamp = $this->stringToNumericTimestamp ($attributes['timestamp']);
				if ($timestamp >= $currentSubmissionTimestamp) {continue;}	// Skip
			}
			
			# If the file location is the same, add to the list
			if ($attributes['directory'] . $attributes['filename'] == $fileLocation) {
				$total++;
				if ($organiseByUser) {
					$moreSubmissionsInSameLocation[$attributes['username']][$submission] = $attributes;
				} else {
					$moreSubmissionsInSameLocation[$submission] = $attributes;
				}
			}
		}
		
		# Return the result
		return array ($moreSubmissionsInSameLocation, $total);
	}
	
	
	# Function to get a purely numeric timestamp
	private function stringToNumericTimestamp ($string)
	{
		# Return the value as an integer
		return (str_replace ('-', '', $string)) + 0;	// +0 reliably casts as an integer
	}
	
	
	# Function to list and review submissions
	private function review ($filename)
	{
		# Start the HTML
		$html = '';
		
		# Show the list if required
		$showList = (!$filename || ($filename && (!isSet ($this->submissions[$filename]))));
		if ($showList) {
			$html .= $this->listSubmissions ($reload = true);
			return $html;
		}
		
		# Ensure that the tree is writable, or end
		if (!$this->treesPotentiallyWritable ($this->currentDirectory, $html)) {
			return $html;
		}
		
		# Create the form itself
		$form = new form (array (
			'submitButtonText' => 'Take action',
			'formCompleteText' => false,
		));
		
		# Define the heading
		$fileDescription = ($this->livePage ? 'updated ' : 'new ') . $this->fileDescription ($filename);
		$fileLocation = $this->submissions[$filename]['directory'] . $this->submissions[$filename]['filename'];
		$form->heading ('', "<p class=\"information\">Please review the proposed <strong>" . $fileDescription . '</strong> below, submitted by ' . $this->convertUsername ($this->submissions[$filename]['username']) . ' on ' . $this->convertTimestamp ($this->submissions[$filename]['timestamp']) . ", and approve if it is acceptable. This is for the location " . (!file_exists ($this->liveSiteRoot . $fileLocation) ? "{$fileLocation} " : "<a title=\"Link opens in a new window\" target=\"_blank\" href=\"{$this->liveSiteUrl}{$fileLocation}\">{$fileLocation}</a>") . '.</p>');
		
		# Determine if there are earlier submissions of the same page
		list ($moreSubmissionsInSameLocation, $total) = $this->moreSubmissionsInSameLocation ($filename);
		
		# Define the actions
		$actions = array (
			'approve-message'	=> 'Approve it (move to live site) and ' . (($this->submissions[$filename]['username'] == $this->user) ? 'e-mail myself as a reminder' : 'inform its creator, ' . $this->convertUsername ($this->submissions[$filename]['username'])) . ' ' . chr (0xe2) . chr (0x80) . chr (0xa0),
			'approve'			=> 'Approve it (move to live site) but send no message',
			'reject-message'	=> 'Reject it (and delete the file) and ' . (($this->submissions[$filename]['username'] == $this->user) ? 'e-mail myself as a reminder' : 'inform its creator, ' . $this->convertUsername ($this->submissions[$filename]['username'])) . ' ' . chr (0xe2) . chr (0x80) . chr (0xa0),
			'reject'			=> 'Reject it (and delete the file) but send no message',
			'edit'				=> "Edit it further now (without sending a message)",
			'message'			=> 'Only send a message to its creator (add a message below) ',
		);
		$form->radiobuttons (array (
			'name'				=> 'action',
			'values'			=> $actions,
			'title'				=> 'Action',
			'required'			=> true,
		));
		if ($moreSubmissionsInSameLocation) {
			$deleteEarlierText = "Also delete earlier submissions ({$total}) of this same page";
			$form->checkboxes (array (
			    'name'			=> 'deleteearlier',
			    'values'			=> array ($deleteEarlierText,),
			    'title'					=> 'Delete earlier submissions?',
				'default' => array ($deleteEarlierText),
				'description' => 'This is independent of whatever action you select above.<br />No e-mail will be sent for these additional submissions.',
			));
		}
		if ($this->allowNewLocation) {
			$form->input (array (
				'name'			=> 'location',
				'title'					=> 'Approve to new location',
				'description'		=> 'If <em>necessary</em>, enter a different URL to approve it to, starting with&nbsp;/&nbsp;.',
				'regexp'		=> '^/',
			));
		}
		$textareaWidget = $this->additionalMessageWidget;
		$textareaWidget['title'] .= ' (&dagger; only)';
		$form->textarea ($textareaWidget);
		
		# Do pre-submission checks
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			
			# Ensure a message is entered if messaging
			if (($unfinalisedData['action'] == 'message') && empty ($unfinalisedData['message'])) {
				$form->registerProblem ('nomessage', "You didn't enter a message!");
			}
			
			# Ensure a specified new location is not in a banned area
			if ($this->allowNewLocation) {
				if ($this->matchLocation ($this->bannedLocations, $unfinalisedData['location'])) {
					$form->registerProblem ('bannedArea', 'Changes cannot be made to pages in the new location you specified.');
				}
			}
		}
		
		# If the form is not processed, show the page
		if (!$result = $form->process ($html)) {
			$fileOnServer = $this->filestoreRoot . $filename;
			chdir (str_replace ('\\', '/', dirname ($fileOnServer)));
			$html .= "\n<hr />";
			$html .= $this->showMaterial ($this->editableFileContents, 'information');
			return $html;
		}
		
		# Flag to mail the user if explicitly requested or an additional message added
		$mailUser = (strpos ($result['action'], 'message') !== false);
		
		# Empty the list of more submissions in the same location if the user doesn't want these deleted
		if ($moreSubmissionsInSameLocation) {
			if (!$result['deleteearlier'][$deleteEarlierText]) {
				$moreSubmissionsInSameLocation = array ();
			}
		}
		$thisUserMoreSubmissionsTotal = (isSet ($moreSubmissionsInSameLocation[$this->submissions[$filename]['username']]) ? count ($moreSubmissionsInSameLocation[$this->submissions[$filename]['username']]) : 0);
		
		# Reject earlier submissions, if any; this is done first so that any listing excludes ones being deleted in the overall operation
		if ($moreSubmissionsInSameLocation) {
			foreach ($moreSubmissionsInSameLocation as $user => $submissions) {
				foreach ($submissions as $moreSubmissionsFilename => $attributes) {
					$html .= $this->reject ($moreSubmissionsFilename, $rejectedOk, $silentMode = true);
					if (!$rejectedOk) {
						return $html;	// Don't continue if there's a problem
					}
				}
			}
			$html .= "\n<p class=\"success\">The earlier submissions of this page were deleted successfully.</p>";
		}
		
		# Take action depending on the result
		switch ($result['action']) {
			case 'approve-message':
			case 'approve':
				$html .= $this->makeLive ($filename, $this->editableFileContents, $madeLiveOk, $directly = false, (isSet ($result['location']) ? $result['location'] : false), $mailUser, $result['message'], $thisUserMoreSubmissionsTotal);
				if ($this->submissions) {$html .= $this->listSubmissions ($reload = true);}
				break;
				
			case 'reject-message':
			case 'reject':
				$html .= $this->reject ($filename, $rejectedOk, $silentMode = false, $mailUser, $result['message'], $thisUserMoreSubmissionsTotal);
				break;
				
			case 'edit':
				# Redirect the user to the new page; take no other action. The previous version will need to be deleted manually by the administrator
				application::sendHeader (302, "{$this->editSiteUrl}{$filename}?edit");
				$html .= "\n<p><a href=\"{$filename}?edit\">Click here to edit the " . ($this->blogMode ? 'blog posting' : 'page') . "</a> (as your browser has not redirected you automatically).</p>";
				break;
				
			case 'message':
				# Send the message
				$file = $this->submissions[$filename];
				$fileLocation = $file['directory'] . $file['filename'];
				$compiledMessage = 'With regard to the ' . ($this->blogMode ? 'blog posting' : 'page') . " you submitted, {$fileLocation}" . html_entity_decode ($file['title'] ? " ({$file['title']})" : ' ') . ", on " . $this->convertTimestamp ($file['timestamp']) . ":\n\n{$result['message']}";
				$html .= $this->sendMail ($this->submissions[$filename]['username'], $compiledMessage, 'message regarding a ' . ($this->blogMode ? 'blog posting' : 'page') . ' you submitted');
				break;
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to reject a file
	private function reject ($filename, &$rejectedOk, $silentMode = false, $mailUser = false, $extraMessage = false, $moreSubmissionsByThisUser = false)
	{
		# Start the HTML
		$html = '';
		
		# Shortcuts
		$fileOnServer = $this->filestoreRoot . $filename;
		$fileLocation = $this->submissions[$filename]['directory'] . $this->submissions[$filename]['filename'];
		
		# Move (to the archive store) or delete the file, depending on whether there is an archive store
		if ($this->archiveRoot) {
			
			# Set the archive location from root
			$archiveLocationFromRoot = $this->archiveRoot . $filename . '.rejected-submission';
			
			# Create the directory if necessary
			if (!$this->makeDirectory (dirname ($archiveLocationFromRoot))) {
				$html .= $this->reportErrors ('Unfortunately, the operation failed - there was a problem creating folders in the archive.', "The proposed new directory was {$archiveLocationFromRoot} .");
				$rejectedOk = false;
				return $html;
			}
			
			# Move the file
			$success = rename ($fileOnServer, $archiveLocationFromRoot);
		} else {
			$success = @unlink ($fileOnServer);
		}
		
		# Show outcome
		if (!$success) {
			$html .= $this->reportErrors ('There was a problem ' . ($this->archiveRoot ? 'archiving' : 'deleting') . ' the rejected file.', "The filename was {$fileOnServer} .");
			$rejectedOk = false;
			return $html;
		}
		
		# Log the change
		$html .= $this->logChange ("Submitted file {$fileLocation} deleted");
		
		# End if silent mode
		if ($silentMode) {
			$rejectedOk = true;
			return $html;
		}
		
		# Reload the submissions database, first caching the submitting user
		$submission = $this->submissions[$filename];
		$fileLocation = $submission['directory'] . $submission['filename'];
		$this->submissions = $this->submissions ();
		
		# Regenerate the menu so that the menu links do not reference the now-deleted file
		$this->menuHtml = $this->generateMenu ($this->page);
		
		# Confirm success
		$html .= "\n<p class=\"success\">The file {$fileLocation} was deleted successfully.</p>";
		
		# Mail the user if required
		if ($mailUser) {
			$compiledMessage = 'The ' . ($this->blogMode ? 'blog posting' : 'page') . " you submitted, {$fileLocation}" . html_entity_decode ($submission['title'] ? " ('{$submission['title']}')" : ' ') . ', on ' . $this->convertTimestamp ($submission['timestamp']) . ', has been rejected and thus deleted.';
			if ($moreSubmissionsByThisUser) {$compiledMessage .= "\n\nThe earlier " . ($moreSubmissionsByThisUser == 1 ? 'version of this page that you submitted has' : 'versions of this page that you submitted have') . ' also been discarded.';}
			if ($extraMessage) {$compiledMessage .= "\n\n{$extraMessage}";}
			$html .= $this->sendMail ($submission['username'], $compiledMessage, $subjectSuffix = ($this->blogMode ? 'blog posting' : 'page') . ' submission rejected');
		}
		
		# Relist the submissions if appropriate
		#!# Reloading is failing here sometimes
		if ($this->submissions) {$html .= $this->listSubmissions ($reload = false);}
		
		# Signal success
		$rejectedOk = true;
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to approve a file (i.e. make live)
	private function makeLive ($submittedFile, $contents, &$madeLiveOk = false, $directly = false, $respecifiedLocation = false, $mailUser = false, $extraMessage = false, $moreSubmissionsByThisUser = false)
	{
		# Start the HTML
		$html = '';
		
		# Construct the file location
		$newFileLiveLocation = ($directly ? $submittedFile : ($respecifiedLocation ? $respecifiedLocation . (substr ($respecifiedLocation, -1) == '/' ? 'index.html' : '') : $this->submissions[$submittedFile]['directory'] . $this->submissions[$submittedFile]['filename']));
		$newFileLiveLocationFromRoot = $this->liveSiteRoot . $newFileLiveLocation;
		
		# Backup replaced live files if necessary
		#!# Refactor to separate function
		if ($this->archiveReplacedLiveFiles) {
			if (file_exists ($newFileLiveLocationFromRoot)) {
				$archiveLocation = $newFileLiveLocation . '.' . date ('Ymd-His');
				$archiveLocationFromRoot = ($this->archiveRoot ? $this->archiveRoot : $this->liveSiteRoot) . $archiveLocation;
				
				# Create the directory if necessary
				if (!$this->makeDirectory (dirname ($archiveLocationFromRoot))) {
					$html .= $this->reportErrors ('Unfortunately, the operation failed - there was a problem creating folders in the archive.', "The proposed new directory was {$archiveLocationFromRoot} .");
					$madeLiveOk = false;
					return $html;
				}
				
				# Copy the file across
				if (!@copy ($newFileLiveLocationFromRoot, $archiveLocationFromRoot)) {
					$html .= $this->reportErrors ('The new ' . ($this->blogMode ? 'blog posting' : 'page') . ' was not approved, as there was a problem archiving the existing file on the live site of the same name.', "This archived file would have been at {$archiveLocationFromRoot} .");
					$madeLiveOk = false;
					return $html;
				}
				$html .= $this->logChange ("Archived existing file on the live site $newFileLiveLocation to $archiveLocationFromRoot");
			}
		}
		
		# Install the new file on the live site
		if (!$installNewFileResult = application::createFileFromFullPath ($newFileLiveLocationFromRoot, $contents, $addStamp = false, $this->user)) {
			$html .= $this->reportErrors ('There was a problem installing the approved file on the live site.', "This new file would have been at $newFileLiveLocation on the live site.");
			$madeLiveOk = false;
			return $html;
		}
		
		/* Disabled as this generates "PHP Warning:  touch(): Utime failed: Operation not permitted"
		# Ensure the file save time is as originally submitted, not the time of approval
		$fileModificationUnixtime = $this->convertTimestamp ($this->submissions[$submittedFile]['timestamp'], true, $asUnixtime = true);
		touch ($newFileLiveLocationFromRoot, $fileModificationUnixtime);
		*/
		
		$html .= $this->logChange (($directly ? 'New ' . ($this->blogMode ? 'blog posting' : 'page') . ' directly' : "Submitted file $submittedFile approved and") . " saved to $newFileLiveLocation on live site");
		$newFileLiveLocationChopped = $this->chopDirectoryIndex ($newFileLiveLocation);
		if ($newFileLiveLocationChopped == '/') {$newFileLiveLocationChopped = '';}
		if ($this->blogMode) {
			$currentBlogRoot = $this->getCurrentBlogRoot ();
		}
		$html .= "<p class=\"success\">The " . ($this->blogMode ? 'blog posting' : 'page') . ' has been approved and is now online, at: ' . ($this->blogMode ? "<a title=\"Link opens in a new window\" target=\"_blank\" href=\"{$this->liveSiteUrl}{$currentBlogRoot}\">{$this->liveSiteUrl}{$currentBlogRoot}</a> or at the posting-specific location of: " : '') . "<a title=\"Link opens in a new window\" target=\"_blank\" href=\"{$this->liveSiteUrl}{$newFileLiveLocationChopped}\">{$this->liveSiteUrl}{$newFileLiveLocationChopped}</a>.</p>";
		
		# Mail the user if required
		if ($mailUser) {
			$fileTimestamp = $this->convertTimestamp ($this->submissions[$submittedFile]['timestamp']);
			$compiledMessage = 'The ' . ($this->blogMode ? 'blog posting' : 'page') . " you submitted, {$newFileLiveLocation}" . html_entity_decode ($this->submissions[$submittedFile]['title'] ? " ('{$this->submissions[$submittedFile]['title']}')" : ' ') . ", on {$fileTimestamp}, has been approved and is now online, at:\n\n{$this->liveSiteUrl}{$newFileLiveLocationChopped}";
			if ($moreSubmissionsByThisUser) {$compiledMessage .= "\n\nThe earlier " . ($moreSubmissionsByThisUser == 1 ? 'version of this page that you submitted has' : 'versions of this page that you submitted have') . ' been discarded.';}
			if ($extraMessage) {$compiledMessage .= "\n\n{$extraMessage}";}
			$html .= $this->sendMail ($this->submissions[$submittedFile]['username'], $compiledMessage, ($this->blogMode ? 'blog posting' : 'page') . ' approved');
		}
		
		# Delete the staging file and log the change
		if (!$directly) {
			if (!@unlink ($this->filestoreRoot . $submittedFile)) {
				$html .= $this->reportErrors ('There was a problem deleting the originally submitted staging file.', "The filename was {$this->filestoreRoot}{$submittedFile} .");
				$madeLiveOk = false;
				return $html;
			}
			$html .= $this->logChange ("Originally submitted (but now live) file {$this->filestoreRoot}{$submittedFile} deleted from filestore.");
		}
		
		# Set the cached result
		$madeLiveOk = ($installNewFileResult);
		
		# If the location has been respecified, regenerate the menu
		if ($respecifiedLocation) {
			$this->menuHtml = $this->generateMenu ($respecifiedLocation);
		}
		
		# Return the HTML
		return $html;
	}
	
	
	
	# Wrapper function to send the administrator an e-mail listing errors
	private function reportErrors ($errors, $privateInfo = false, $silently = false)
	{
		# Start the HTML
		$html = '';
		
		# Ensure the errors are an array
		$errors = application::ensureArray ($errors);
		
		# Show the errors if required
		if (!$silently) {
			foreach ($errors as $error) {
				$html .= "\n<p class=\"failure\">{$error}</p>";
			}
		}
		
		# Do not attempt to mail the administrator if no administrator address is available (which could be why an error is being thrown)
		if (!$this->serverAdministrator) {return $html;}
		
		# Construct the message; note that $this->users may not yet exist so it can't be used to get the user's real name
		$introduction = 'The following ' . (count ($errors) == 1 ? 'problem was' : 'problems were') . ' encountered' . ($this->user ? " (by user {$this->user})" : '') . ':';
		$message = "\nDear webserver administrator,\n\n{$introduction}\n\n" . '- ' . implode ("\n\n- ", $errors);
		
		# If there is private information, add this
		if ($privateInfo) {$message .= "\n\nAdditional diagnostic information:\n" . $privateInfo;}
		
		# Add the current page
		$message .= "\n\n\nThis message was generated from the following URL:\n" . $_SERVER['_PAGE_URL'];
		
		# Send the mail
		$html .= $this->sendMail ($this->serverAdministrator, $message, $subjectSuffix = (count ($errors) == 1 ? 'error' : 'errors') . ' occured - please investigate', false, $showMessageOnScreen = false, $messageSentOk);
		if ($messageSentOk) {
			$html .= '<p class="information">The server administrator has been informed about ' . (count ($errors) == 1 ? 'this error' : 'these errors') . '.</p>';
		}
		
		# Clear the HTML if sending silently
		if ($silently) {$html = '';}
		
		
		# Return the HTML
		return $html;
	}
	
	
	# Wrapper function to send e-mail
	private function sendMail ($users, $message, $subjectSuffix = false, $includeUrl = false, $showMessageOnScreen = true, &$messageSentOk = false)
	{
		# Start the HTML
		$html = '';
		
		# Start an array of users and their names
		$to = array ();
		$name = array ();
		
		# If the user is actually an e-mail address, assign this directly; otherwise obtain attributes
		if ($users == $this->serverAdministrator) {
			$to[] = $this->serverAdministrator;
			$fromHeader = 'From: ' . $this->serverAdministrator;
		} else {
			
			# Loop through each user supplied
			$users = application::ensureArray ($users);
			
			# Get the user's/users' e-mail address and define the From header also
			foreach ($users as $user) {
				if (!isSet ($this->users[$user])) {continue;}
				$to[] = $this->formatEmailAddress ($user);
				$name[] = $this->users[$user]['Forename'];
			}
			$fromHeader = 'From: ' . $this->formatEmailAddress ($this->user);
			
			# Compile the name (commas between, except for 'and' between last two if there is more than one
			$nameMessage = '';
			$finalName = array_pop ($name);
			if ($name) {
				$nameMessage .= implode (', ', $name);
				$nameMessage .= ' and ';
			}
			$nameMessage .= $finalName;
			
			# Add the user's name to the message, the signature, and login details
			$message  = "\nDear " . $nameMessage . ",\n\n" . $message;
			$message .= "\n\n\n" . $this->messageSignatureGreeting . "\n" . $this->convertUsername ($this->user);
			if ($includeUrl) {$message .= "\n\n\nMessage sent from page:\n" . $_SERVER['_PAGE_URL'];}
			$message .= "\n\n\n--\nAuthorised users can log into the pureContentEditor system at {$this->editSiteUrl}/ , using their {$this->authTypeName} username and password.";
		}
		
		# At this point, perform check that the to(s) and from exist before trying to send it!
		if (!$to) {
			$messageSentOk = true;
			return $html;
		}
		
		# Compile the recipients
		$recipientList = implode (', ', $to);
		
		# Send the mail; ensure the editSiteUrl is set (it may not be if this function is being thrown by reportErrors())
		$subject = ($this->websiteName ? $this->websiteName : $this->liveSiteUrl) . ' website editing facility' . ($subjectSuffix ? ': ' . $subjectSuffix : '');
		if (!application::utf8Mail ($recipientList, $subject, wordwrap ($message), $fromHeader)) {
			$html .= "\n<p class=\"failure\">There was a problem sending an e-mail to the user.</p>";
			$messageSentOk = false;
			return $html;
		}
		
		# Print the message if necessary
		if ($showMessageOnScreen) {
			$html .= "\n<p class=\"success\">The following e-mail message has been sent:</p>";
			$html .= "\n<blockquote><pre>";
			$html .= "\n" . htmlspecialchars ($fromHeader);
			$html .= "\n<strong>" . wordwrap ('To: ' . htmlspecialchars ($recipientList)) . '</strong>';
			$html .= "\n" . wordwrap ('Subject: ' . htmlspecialchars ($subject)) . '</strong>';
			$html .= "\n\n" . wordwrap (htmlspecialchars ($message));
			$html .= "\n</pre></blockquote>";
		}
		
		# Signal success
		$messageSentOk = true;
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to return a formatted e-mail string usable in mail (), given the username
	private function formatEmailAddress ($user)
	{
		# Ensure the user exists
		#!# This means an empty result being set in the caller
		if (!isSet ($this->users[$user])) {return false;}
		
		# Get the address
		$attributes = $this->users[$user];
		
		# Construct the string, surrounding it with the name if not on a non-Windows platform
		$string = $attributes['E-mail'];
		if ((PHP_OS != 'WINNT') && $this->nameInEmail) {
			$string = "{$attributes['Forename']} {$attributes['Surname']} <{$string}>";
		}
		
		# Return the string
		return $string;
	}
	
	
	# Function to get a description of the file
	private function fileDescription ($filename)
	{
		# Get the file metadata
		$fileData = $this->submissions[$filename];
		
		# Section title file
		if ($fileData['filename'] == $this->pureContentTitleFile) {
			
			$description = "section title for the section <a target=\"_blank\" href=\"{$fileData['directory']}\">{$fileData['directory']}</a>";
			return $description;
		}
		
		# Submenu file
		if ($fileData['filename'] == $this->pureContentSubmenuFile) {return 'contents of the submenu list';}
		
		# HTML page
		if ($fileData['extension'] == 'html') {return 'page';}
		
		# Else return a generic reference
		return 'submission';
	}
	
	
	# Function to list the awaiting submissions
	private function listSubmissions ($reload = false)
	{
		# Reload the list, excluding template files
		if ($reload) {$this->submissions = $this->submissions ();}
		
		# If there are no files awaiting review, say so and finish
		if (!$this->submissions) {
			$html  = "\n<p class=\"success\">There are no pages awaiting review at present.</p>";
			return $html;
		}
		
		# Start a table of data
		$html  = "\n<p>The following pages are awaiting review:</p>";
		$html .= "\n" . '<table class="lines">';
		$html .= "\n\t" . '<tr>';
		$html .= "\n\t\t" . '<th>File location</th>';
		$html .= "\n\t\t" . '<th>Title</th>';
		$html .= "\n\t\t" . '<th>Submitted by</th>';
		$html .= "\n\t\t" . '<th>Time/date</th>';
		$html .= "\n\t" . '</tr>';
		
		# Loop through each file to create the table (these will automatically be ordered by filename then datestamp)
		foreach ($this->submissions as $file => $attributes) {
			
			# Clean the location
			$location = $attributes['directory'] . $attributes['filename'];
			
			# Create a table row
			$html .= "\n\t" . '<tr>';
			$html .= "\n\t\t" . "<td><a" . ($this->reviewPagesOpenNewWindow ? ' target="blank"': '') . " href=\"{$file}?review\">$location</a></td>";
			$html .= "\n\t\t" . '<td>' . ($attributes['title'] ? $attributes['title'] : '<span class="comment">[No title]</span>') . '</td>';
			$html .= "\n\t\t" . '<td>' . (($this->user == $attributes['username']) ? 'Myself' : $this->convertUsername ($attributes['username'])) . '</td>';
			$html .= "\n\t\t" . '<td>' . $this->convertTimestamp ($attributes['timestamp']) . '</td>';
			$html .= "\n\t" . '</tr>';
		}
		$html .= "\n" . '</table>';
		
		# Return the list
		return $html;
	}
	
	
	# Function to get a human-readable username
	private function convertUsername ($user, $withUserId = true, $indicateAdministrator = false)
	{
		# Return the username without modification if they have gone
		if (!isSet ($this->users[$user])) {return $user;}
		
		# Return the formatted string
		return $this->users[$user]['Forename'] . ' ' . $this->users[$user]['Surname'] . ($withUserId ? " ($user)" : '') . (($indicateAdministrator && $this->users[$user]['Administrator']) ? ' [Administrator]' : '');
	}
	
	
	# Function to get all submissions
	private function submissions ()
	{
		# Get the file listing, excluding files matching the template
		$files = directories::flattenedFileListing ($this->filestoreRoot, array (), $includeRoot = false);
		
		# Filter and organise the file listing
		$files = $this->submissionsFiltered ($files);
		
		# Add titles to each
		foreach ($files as $file => $attributes) {
			$files[$file]['title'] = htmlspecialchars ($this->getTitle ($file));
		}
		
		# Return the list
		return $files;
	}
	
	
	# Wrapper function to get the title of a page
	#!# Needs to have page/file/directory type checking and cover these types also
	private function getTitle ($file)
	{
		# Determine if this is a title file
		$delimiter = '@';
		$isTitleFile = (preg_match ($delimiter . '^' . addcslashes ($this->pureContentTitleFile, $delimiter) . $delimiter, basename ($file)));
		
		# Load the contents of a title file
		if ($isTitleFile) {
			$title = file_get_contents ($this->filestoreRoot . $file);
		} else {
			
			# Load the page and scan for the title
			#!# Inefficient - needs to grab just the first 100 bytes or so
			#!# Add more options to getTitleFromFileContents
			#!# Does this is need file handling?
			$contents = file_get_contents ($this->filestoreRoot . $file);
			$title = application::getTitleFromFileContents ($contents);
		}
		
		# Return the title
		return $title;
	}
	
	
	# Function to filter and organise the file listing
	private function submissionsFiltered ($files, $extensions = array ('html', 'txt', 'css', 'js'))
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
			
			# Add in the file size
			$fullPath = $this->filestoreRoot . $file;
			$attributes['size'] = filesize ($fullPath);
			
			# Assign the information to the list of validated files
			$validatedFiles[$file] = $attributes;
		}
		
		# Return the validated files
		return $validatedFiles;
	}
	
	
	# Function to clean up the directory structure by removing empty directories
	private function cleanUp ()
	{
		# Start the HTML
		$html = '';
		
		# Delete empty directories across the tree
		if ($problemsFound = directories::deleteEmptyDirectories ($this->filestoreRoot)) {
			$html .= $this->reportErrors ('Problems were encountered when attempting to delete empty folders in the filestore.', "The list of directories which did not delete is:\n" . implode ("\n", $problemsFound), true);
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get the house style files
	private function getHouseStyleFiles ()
	{
		# Determine the allowable types
		$supportedFileTypes = array ('html', 'php', 'js', 'css');
		
		# Get the listing
		$files = directories::flattenedFileListingFromArray ($this->technicalFileLocations, $this->liveSiteRoot, $supportedFileTypes);
		
		# Return the files array
		return $files;
	}
	
	
	# Function to enable editing of the house style pages
	private function houseStyle ()
	{
		# Start the HTML
		$html = '';
		
		# End if there are no files
		if (!$files = $this->getHouseStyleFiles ()) {
			$html .= "\n<p>There are no technical files available for editing under this system.</p>";
			return $html;
		}
		
		# Allocate names used by pureContent
		$pureContentNames = array (
			'/.htaccess' => 'Main .htaccess file for site',
			'/robots.txt' => 'Instructions for search engines',
			'/sitetech/403.html' => "'403 access denied' page",
			'/sitetech/404.html' => "'404 page not found' page",
			'/sitetech/appended.html' => 'Footer (appended) file',
			'/sitetech/generic.css' => 'Generic stylesheet',
			'/sitetech/global.css' => 'Main stylesheet',
			'/sitetech/library.js' => 'Javascript library',
			'/sitetech/menu.html' => 'Main menu file',
			'/sitetech/prepended.html' => 'Header (prepended) file',
			'/sitetech/purecontenteditor-htaccess/purecontenteditor.html' => 'pureContentEditor bootstrapping file, for an instance using htaccess authentication',
			'/sitetech/purecontenteditor/purecontenteditor.html' => 'pureContentEditor bootstrapping file, for an instance using default authentication',
		);
		
		# Convert the list to being links
		$links = array ();
		foreach ($files as $location => $file) {
			$links[] = "<a href=\"{$location}?edit\">{$location}</a>" . (isSet ($pureContentNames[$location]) ? " - {$pureContentNames[$location]}" : '');
		}
		
		# Assemble the HTML
		$html .= "\n<p>This section lets you edit the central, house style / technical files which are central to the running of the site.</p>";
		$html .= "\n<p class=\"warning\"><strong>Warning: You should only edit these files if you know what you are doing. Mistakes will affect the whole site.</strong></p>";
		$html .= "\n<p>Note that some files may not be editable depending on the configuration of the webserver.</p>";
		$html .= application::htmlUl ($links);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to provide a message form
	private function message ()
	{
		# Start the HTML
		$html = '';
		
		# Get the administrators
		$users = array ();
		foreach ($this->users as $user => $attributes) {
			
			# Ensure that the current user cannot send a mesage to themselves
			if ($this->user == $user) {continue;}
			
			# If the user is not an administrator, do not list non-administrators
			if (!$this->userIsAdministrator && !$attributes['Administrator']) {continue;}
			
			# Add the user to the list
			$users[$user] = $this->convertUsername ($user, true, true);
		}
		
		# Finish if there are no users to send messages to
		if (!$users) {
			$html .= "\n<p class=\"information\">There are no users to whom messages can be sent.</p>";
			return $html;
		}
		
		# Create the form itself
		$form = new form (array (
			'submitButtonText' => 'Send message',
			'displayDescriptions' => false,
			'formCompleteText' => false,
		));
		
		# Form widgets
		$form->select (array (
			'name'			=> 'username',
			'values'			=> $users,
			'title'					=> 'Send message to',
			'required'		=> 1,
		));
		$form->textarea (array (
			'name'			=> 'message',
			'title'					=> 'Message',
			'required'				=> true,
			'cols'				=> 40,
		));
		
		# Send the message
		if ($result = $form->process ($html)) {
			$html .= $this->sendMail ($result['username'], $result['message'], $subjectSuffix = 'message', $includeUrl = true);
		}
		
		# Return the HTML
		return $html;
	}
}


?>
