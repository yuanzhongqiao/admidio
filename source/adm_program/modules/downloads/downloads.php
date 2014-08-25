<?php
/******************************************************************************
 * Show a list of all downloads
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * folder_id : Id of the current folder that should be shown
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/file_extension_icons.php');

unset($_SESSION['download_request']);

$buffer = '';

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'numeric', 0);

// Check if module is activated
if ($gPreferences['enable_download_module'] != 1)
{
    // Module is not activated
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// Only available from master organization
if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) != 0)
{
    // is not master organization
    $gMessage->show($gL10n->get('SYS_MODULE_ACCESS_FROM_HOMEPAGE_ONLY', $gHomepage));
}

try
{
    // get recordset of current folder from databse
    $currentFolder = new TableFolder($gDb);
    $currentFolder->getFolderForDownload($getFolderId);
}
catch(AdmException $e)
{
	$e->showHtml();
}

// set headline of the script
if($currentFolder->getValue('fol_fol_id_parent') == null)
{
    $headline = $gL10n->get('DOW_DOWNLOADS');
}
else
{
    $headline = $gL10n->get('DOW_DOWNLOADS').' - '.$currentFolder->getValue('fol_name');
}

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

$getFolderId = $currentFolder->getValue('fol_id');

// Get folder content for style
$folderContent = $currentFolder->getFolderContentsForDownload();

// Keep navigation link 
$navigationBar = $currentFolder->getNavigationForDownload();

// create html page object
$page = new HtmlPage();

$page->addJavascript('$("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});', true);
    
// create module menu
$DownloadsMenu = new ModuleMenu('admMenuDownloads');

if ($gCurrentUser->editDownloadRight())
{
    // show links for upload, create folder and folder configuration
    $DownloadsMenu->addItem('admMenuItemCreateFolder', $g_root_path.'/adm_program/modules/downloads/folder_new.php?folder_id='.$getFolderId,
                        $gL10n->get('DOW_CREATE_FOLDER'), 'folder_create.png' );

    $DownloadsMenu->addItem('admMenuItemAddFile', $g_root_path.'/adm_program/modules/downloads/upload.php?folder_id='.$getFolderId,
                        $gL10n->get('DOW_UPLOAD_FILE'), 'page_white_upload.png' );

    $DownloadsMenu->addItem('admMenuItemConfigFolder', $g_root_path.'/adm_program/modules/downloads/folder_config.php?folder_id='.$getFolderId,
                        $gL10n->get('SYS_AUTHORIZATION'), 'lock.png' );
};

if($gCurrentUser->isWebmaster())
{
	// show link to system preferences of weblinks
	$DownloadsMenu->addItem('admMenuItemPreferencesLinks', $g_root_path.'/adm_program/administration/organization/organization.php?show_option=downloads', 
						$gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png');
}

//Create table object
$downloadOverview = new HtmlTable('tbl_downloads', $page, true, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_TYPE'),
    '<img class="icon-information" src="'. THEME_PATH. '/icons/download.png" alt="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'" title="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'" />',
    $gL10n->get('SYS_NAME'),
    $gL10n->get('SYS_DATE_MODIFIED'),
    $gL10n->get('SYS_SIZE'),
    $gL10n->get('DOW_COUNTER')
);

if ($gCurrentUser->editDownloadRight())
{
    $columnHeading[] = $gL10n->get('SYS_FEATURES');
}

$downloadOverview->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right', 'right', 'left'));
$downloadOverview->addRowHeadingByArray($columnHeading);
$downloadOverview->setMessageIfNoRowsFound('DOW_FOLDER_NO_FILES');

// Get folder content
if (isset($folderContent['folders'])) 
{
    // First get possible sub folders
    for($i=0; $i<count($folderContent['folders']); $i++) 
    {
        $nextFolder = $folderContent['folders'][$i];
        error_log($nextFolder['fol_id'].'::'.$nextFolder['fol_name'].'::'.$nextFolder['fol_exists']);
        $folderDescription = '';
        if($nextFolder['fol_description'] != '')
        {
            $folderDescription = '<a class="icon-link" href="#"><img src="'. THEME_PATH. '/icons/info.png" alt="'.$nextFolder['fol_description'].'" title="'.$nextFolder['fol_description'].'" /></a>';
        }
        // create array with all column values
        $columnValues = array(
            1, // Type folder
            '<a class="icon-link" href="'.$g_root_path.'/adm_program/modules/downloads/downloads.php?folder_id='. $nextFolder['fol_id']. '">
                                        <img src="'. THEME_PATH. '/icons/download.png" alt="'.$gL10n->get('SYS_FOLDER').'" title="'.$gL10n->get('SYS_FOLDER').'" /></a>',
            '<a href="'.$g_root_path.'/adm_program/modules/downloads/downloads.php?folder_id='. $nextFolder['fol_id']. '">'. $nextFolder['fol_name']. '</a>'.$folderDescription,
            '&nbsp;',
            '&nbsp;',
            '&nbsp;'
        );

        if ($gCurrentUser->editDownloadRight())
        {
            //Links for change and delete
            $noteFolderNotExists = '';
            
            if($nextFolder['fol_exists'] == false)
            {
                $noteFolderNotExists = '
                <a class="icon-link colorbox-dialog" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DOW_FOLDER_NOT_EXISTS&amp;inline=true"
                    data-toggle="tooltip" data-html="true" data-original-title="'.$gL10n->get('DOW_FOLDER_NOT_EXISTS').'"><img
                    src="'. THEME_PATH. '/icons/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" /></a>';
            }

            $columnValues[] = '<a class="icon-link" href="'.$g_root_path.'/adm_program/modules/downloads/rename.php?folder_id='. $nextFolder['fol_id']. '">
                                        <img src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
                                        <a class="icon-link" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=fol&amp;element_id=row_folder_'.
                                        $nextFolder['fol_id'].'&amp;name='.urlencode($nextFolder['fol_name']).'&amp;database_id='.$nextFolder['fol_id'].'"><img
                                        src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>'. 
                                        $noteFolderNotExists; 
        }
        $downloadOverview->addRowByArray($columnValues, 'row_folder_'.$nextFolder['fol_id']);
    }
}

// Get contained files
if (isset($folderContent['files'])) 
{
    for($i=0; $i<count($folderContent['files']); $i++) 
    {
        $nextFile = $folderContent['files'][$i];

        // Check filetyp
        $fileExtension  = admStrToLower(substr($nextFile['fil_name'], strrpos($nextFile['fil_name'], '.')+1));

        // Choose icon for the file
        $iconFile = 'page_white_question.png';
        if(array_key_exists($fileExtension, $icon_file_extension))
        {
            $iconFile = $icon_file_extension[$fileExtension];
        }
        
        // Format timestamp
        $timestamp = new DateTimeExtended($nextFile['fil_timestamp'], 'Y-m-d H:i:s');

        $fileDescription = '';
        if($nextFile['fil_description']!='')
        {
            $fileDescription = '<span class="icon-link" ><a class="textTooltip" title="'.$nextFile['fil_description'].'" href="#"><img src="'. THEME_PATH. '/icons/info.png" alt="'.$gL10n->get('SYS_FILE').'"/></a></span>';
        }
        
        // create array with all column values
        $columnValues = array(
            2, // Type file
            '<a class="icon-link" href="'.$g_root_path.'/adm_program/modules/downloads/get_file.php?file_id='. $nextFile['fil_id']. '">
                <img src="'. THEME_PATH. '/icons/'.$iconFile.'" alt="'.$gL10n->get('SYS_FILE').'" title="'.$gL10n->get('SYS_FILE').'" /></a>',     
            '<a href="'.$g_root_path.'/adm_program/modules/downloads/get_file.php?file_id='. $nextFile['fil_id']. '">'. $nextFile['fil_name']. '</a>'.$fileDescription,
            $timestamp->format($gPreferences['system_date'].' '.$gPreferences['system_time']),
            $nextFile['fil_size']. ' kB&nbsp;',
            ($nextFile['fil_counter'] != '') ? $nextFile['fil_counter'] : '0'
        );
        
        if ($gCurrentUser->editDownloadRight())
        {
            //Links for change and delete
            $noteFileNotExists = '';
            
            if($nextFile['fil_exists'] == false)
            {
                $noteFileNotExists = '
                <a class="icon-link colorbox-dialog" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DOW_FILE_NOT_EXISTS&amp;inline=true"
                    data-toggle="tooltip" data-html="true" data-original-title="'.$gL10n->get('DOW_FILE_NOT_EXISTS').'"><img
                    src="'. THEME_PATH. '/icons/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" /></a>';
            }
            $columnValues[] = '
            <a class="icon-link" href="'.$g_root_path.'/adm_program/modules/downloads/rename.php?file_id='. $nextFile['fil_id']. '">
                <img src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
            <a class="icon-link" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=fil&amp;element_id=row_file_'.
                $nextFile['fil_id'].'&amp;name='.urlencode($nextFile['fil_name']).'&amp;database_id='.$nextFile['fil_id'].'"><img
                src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>'.
            $noteFileNotExists;
        }
        $downloadOverview->addRowByArray($columnValues, 'row_file_'.$nextFile['fil_id']);
    }
}
    
//Create download table
$downloadOverview->setDatatablesHideColumns(array(1));
$downloadOverview->setDatatablesOrderColumns(array(1, 3));
$htmlDownloadOverview = $downloadOverview->show(false);


/**************************************************************************/
// Add Admin table to html page
/**************************************************************************/

//If user is download Admin show further files contained in this folder.
if ($gCurrentUser->editDownloadRight())
{
    // Check whether additional content was found in the folder
    if (isset($folderContent['additionalFolders']) || isset($folderContent['additionalFiles']))
    {

        $htmlAdminTableHeadline = '<h2 class="admHeadline2">
                                    '.$gL10n->get('DOW_UNMANAGED_FILES').'
                                    <a class="icon-link colorbox-dialog" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DOW_ADDITIONAL_FILES&amp;inline=true"
                                        data-toggle="tooltip" data-html="true" data-original-title="'.$gL10n->get('DOW_ADDITIONAL_FILES').'"><img 
                                        src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>            
                                    </h2>';

        //Create table object
        $adminTable = new HtmlTable('tbl_downloads', $page, true);

        // create array with all column heading values
        $columnHeading = array('<img class="icon-information" src="'. THEME_PATH. '/icons/download.png" alt="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'" title="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'" />',
                               $gL10n->get('SYS_NAME'),
                               $gL10n->get('SYS_FEATURES'));
        $adminTable->addRowHeadingByArray($columnHeading);

        // Get folders
        if (isset($folderContent['additionalFolders'])) 
        {
            for($i=0; $i<count($folderContent['additionalFolders']); $i++) 
            {

                $nextFolder = $folderContent['additionalFolders'][$i];

                $columnValues = array('<img src="'. THEME_PATH. '/icons/download.png" alt="'.$gL10n->get('SYS_FOLDER').'" title="'.$gL10n->get('SYS_FOLDER').'" />',
                                      $nextFolder['fol_name'],
                                      '<a class="icon-link" href="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=6&amp;folder_id='.$getFolderId.'&amp;name='. urlencode($nextFolder['fol_name']). '">
                                          <img src="'. THEME_PATH. '/icons/database_in.png" alt="'.$gL10n->get('DOW_ADD_TO_DATABASE').'" title="'.$gL10n->get('DOW_ADD_TO_DATABASE').'" /></a>');
                $adminTable->addRowByArray($columnValues);
            }
        }

        // Get files
        if (isset($folderContent['additionalFiles'])) 
        {
            for($i=0; $i<count($folderContent['additionalFiles']); $i++) 
            {

                $nextFile = $folderContent['additionalFiles'][$i];

                // Get filetyp
                $fileExtension  = admStrToLower(substr($nextFile['fil_name'], strrpos($nextFile['fil_name'], '.')+1));

                // Choose icon for the file
                $iconFile = 'page_white_question.png';
                if(array_key_exists($fileExtension, $icon_file_extension))
                {
                    $iconFile = $icon_file_extension[$fileExtension];
                }

                $columnValues = array('<img src="'. THEME_PATH. '/icons/'.$iconFile.'" alt="'.$gL10n->get('SYS_FILE').'" title="'.$gL10n->get('SYS_FILE').'" /></a>',
                                      $nextFile['fil_name'],
                                      '<a class="icon-link" href="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=6&amp;folder_id='.$getFolderId.'&amp;name='. urlencode($nextFile['fil_name']). '">
                                          <img src="'. THEME_PATH. '/icons/database_in.png" alt="'.$gL10n->get('DOW_ADD_TO_DATABASE').'" title="'.$gL10n->get('DOW_ADD_TO_DATABASE').'" /></a>');
                $adminTable->addRowByArray($columnValues);
            }
        }
        $htmlAdminTable = $adminTable->show(false);
    }
}

// Output module html to client

$page->addHtml($navigationBar);

$page->addHeadline($headline);

$page->addHtml($DownloadsMenu->show(false));

$page->addHtml($htmlDownloadOverview);

// if user has admin download rights, then show admin table for undefined files in folders
if(isset($htmlAdminTable))
{
    $page->addHtml($htmlAdminTableHeadline);
    $page->addHtml($htmlAdminTable);
}

// show html of complete page
$page->show();

?>