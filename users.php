<?php
/**
 *
 * @file          users.php
 * @author        Nils Laumaillé
 * @version       2.1.23
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key']))
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], curPage())) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

// Load file
require_once 'users.load.php';
// load help
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'_admin_help.php';

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

$treeDesc = $tree->getDescendants();
// Build FUNCTIONS list
$rolesList = array();
$rows = DB::query("SELECT id,title FROM ".prefix_table("roles_title")." ORDER BY title ASC");
foreach ($rows as $reccord) {
    $rolesList[$reccord['id']] = array('id' => $reccord['id'], 'title' => $reccord['title']);
}
// Display list of USERS
echo '
<div class="title ui-widget-content ui-corner-all">
    '.$LANG['admin_users'].'&nbsp;&nbsp;&nbsp;
    <img src="includes/images/refresh.png" title="'.$LANG['reload_table'].'" onclick="reloadUsersList()"class="button" style="padding:2px;" />
    <img src="includes/images/user--plus.png" title="'.$LANG['new_user_title'].'" onclick="OpenDialog(\'add_new_user\')"class="button" style="padding:2px;" />
    <span style="float:right;margin-right:5px;"><img src="includes/images/question-white.png" style="cursor:pointer" title="'.$LANG['show_help'].'" onclick="OpenDialog(\'help_on_users\')" /></span>
<input type="text" name="search" id="search" />
    <span id="users_list_load" style="display:none; font-size:16px; font-family:arial;">'.$LANG['please_wait'].'&nbsp;<i class="fa fa-cog fa-spin fa-lg"></i>&nbsp;</span>
</div>';

echo '
<form name="form_utilisateurs" method="post" action="">
    <div style="line-height:20px;"  align="center">
        <table cellspacing="0" cellpadding="2">
            <thead>
                <tr>
                    <th width="20px">ID</th>
                    <th></th>
                    <th>'.$LANG['user_login'].'</th>
                    <th>'.$LANG['name'].'</th>
                    <th>'.$LANG['lastname'].'</th>
                    <th>'.$LANG['managed_by'].'</th>
                    <th>'.$LANG['functions'].'</th>
                    <th>'.$LANG['authorized_groups'].'</th>
                    <th>'.$LANG['forbidden_groups'].'</th>
                    <th title="'.$LANG['god'].'"><img src="includes/images/user-black.png" /></th>
                    <th title="'.$LANG['gestionnaire'].'"><img src="includes/images/user-worker.png" /></th>
                    <th title="'.$LANG['read_only_account'].'"><img src="includes/images/user_read_only.png" /></th>
                    <th title="'.$LANG['can_create_root_folder'].'"><img src="includes/images/folder-network.png" /></th>
                    ', (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1) ?
                    	'<th title="'.$LANG['enable_personal_folder'].'"><img src="includes/images/folder-open-document-text.png" /></th>' : ''
                    ,'
                    <th title="'.$LANG['user_action'].'"><img src="includes/images/user-locked.png" /></th>
                    <th title="'.$LANG['pw_change'].'"><img src="includes/images/lock__pencil.png" /></th>
                    <th title="'.$LANG['email_change'].'"><img src="includes/images/mail.png" /></th>
                    <th title="'.$LANG['logs'].'"><img src="includes/images/log.png" /></th>
					', (isset($_SESSION['settings']['2factors_authentication']) && $_SESSION['settings']['2factors_authentication'] == 1) ?
                    	'<th title="'.$LANG['send_ga_code'].'"><img src="includes/images/telephone.png" /></th>':''
                	,'
                </tr>
            </thead>
            <tbody id="tbody_users">';

echo '
            </tbody>
        </table>
    </div>
</form>
<input type="hidden" id="selected_user" />
<input type="hidden" id="log_page" value="1" />';
// DIV FOR CHANGING FUNCTIONS
echo '
<div id="change_user_functions" style="display:none;">' .
$LANG['change_user_functions_info'].'
<form name="tmp_functions" action="">
<div id="change_user_functions_list" style="margin-left:15px;"></div>
</form>
</div>';
// DIV FOR CHANGING AUTHORIZED GROUPS
echo '
<div id="change_user_autgroups" style="display:none;">' .
$LANG['change_user_autgroups_info'].'
<form name="tmp_autgroups" action="">
<div id="change_user_autgroups_list" style="margin-left:15px;"></div>
</form>
</div>';
// DIV FOR CHANGING FUNCTIONS
echo '
<div id="change_user_forgroups" style="display:none;">' .
$LANG['change_user_forgroups_info'].'
<form name="tmp_forgroups" action="">
<div id="change_user_forgroups_list" style="margin-left:15px;"></div>
</form>
</div>';
// DIV FOR CHANGING ADMINISTRATED BY
echo '
<div id="change_user_adminby" style="display:none;">
    <div id="change_user_adminby_list" style="margin:20px 0 0 15px;">
        <select id="user_admin_by" class="input_text text ui-widget-content ui-corner-all">
            <option value="0">'.$LANG['administrators_only'].'</option>';
    foreach ($rolesList as $fonction) {
        if ($_SESSION['is_admin'] || in_array($fonction['id'], $_SESSION['user_roles'])) {
            echo '
            <option value="'.$fonction['id'].'">'.$LANG['managers_of'].' "'.$fonction['title'].'"</option>';
        }
    }
    echo '
        </select>
    </div>
</div>';

/* DIV FOR ADDING A USER */
echo '
<div id="add_new_user" style="display:none;">
    <div id="add_new_user_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <label for="new_name" class="label_cpm">'.$LANG['name'].'</label>
    <input type="text" id="new_name" class="input_text text ui-widget-content ui-corner-all" onchange="loginCreation()" />
    <br />
    <label for="new_lastname" class="label_cpm">'.$LANG['lastname'].'</label>
    <input type="text" id="new_lastname" class="input_text text ui-widget-content ui-corner-all" onchange="loginCreation()" />
    <br />
    <label for="new_login" class="label_cpm">'.$LANG['login'].'</label>
    <input type="text" id="new_login" class="input_text text ui-widget-content ui-corner-all" />
    <br />
    ', isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 ? '' :
'<label for="new_pwd" class="label_cpm">'.$LANG['pw'].'&nbsp;<img src="includes/images/refresh.png" onclick="pwGenerate(\'new_pwd\')" style="cursor:pointer;" /></label>
    <input type="text" id="new_pwd" class="input_text text ui-widget-content ui-corner-all" />', '
    <label for="new_email" class="label_cpm">'.$LANG['email'].'</label>
    <input type="text" id="new_email" class="input_text text ui-widget-content ui-corner-all" onchange="check_domain(this.value)" />
    <label for="new_is_admin_by" class="label_cpm">'.$LANG['is_administrated_by_role'].'</label>
    <select id="new_is_admin_by" class="input_text text ui-widget-content ui-corner-all">';
        // If administrator then all roles are shown
        // else only the Roles the users is associated to.
if ($_SESSION['is_admin']) {
    echo '
        <option value="0">'.$LANG['administrators_only'].'</option>';
}
foreach ($rolesList as $fonction) {
    if ($_SESSION['is_admin'] || in_array($fonction['id'], $_SESSION['user_roles'])) {
        echo '
        <option value="'.$fonction['id'].'">'.$LANG['managers_of'].' "'.$fonction['title'].'"</option>';
    }
}
echo '
    </select>
    <br />
    <input type="checkbox" id="new_admin"', $_SESSION['user_admin'] == 1 ? '':' disabled', ' />
       <label for="new_admin">'.$LANG['is_admin'].'</label>
    <br />
    <input type="checkbox" id="new_manager"', $_SESSION['user_admin'] == 1 ? '':' disabled', ' />
       <label for="new_manager">'.$LANG['is_manager'].'</label>
    <br />
    <input type="checkbox" id="new_read_only" />
       <label for="new_read_only">'.$LANG['is_read_only'].'</label>
    <br />
    <input type="checkbox" id="new_personal_folder"', isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 ? ' checked':'', ' />
       <label for="new_personal_folder">'.$LANG['personal_folder'].'</label>
    <div id="auto_create_folder_role">
    <input type="checkbox" id="new_folder_role_domain" disabled />
    <label for="new_folder_role_domain">'.$LANG['auto_create_folder_role'].'&nbsp;`<span id="auto_create_folder_role_span"></span>`</label>
    <img id="ajax_loader_new_mail" style="display:none;" src="includes/images/ajax-loader.gif" alt="" />
    <input type="hidden" id="new_domain">
    </div>
    <div style="display:none;" id="add_new_user_info" class="ui-state-default ui-corner-all"></div>
</div>';
// DIV FOR DELETING A USER
echo '
<div id="delete_user" style="display:none;">
    <div id="user_action_html"></div>
    <div style="font-weight:bold;text-align:center;color:#FF8000;text-align:center;font-size:13pt;" id="delete_user_show_login"></div>
    <input type="hidden" id="delete_user_login" />
    <input type="hidden" id="delete_user_id" />
    <input type="hidden" id="delete_user_action" />
</div>';
// DIV FOR CHANGING PASWWORD
echo '
<div id="change_user_pw" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="change_user_pw_error"></div>' .
$LANG['give_new_pw'].'
    <div style="font-weight:bold;text-align:center;color:#FF8000;display:inline;" id="change_user_pw_show_login"></div>
    <div style="margin-top:20px; width:100%;">
        <label class="form_label" for="change_user_pw_newpw">'.$LANG['index_new_pw'].'</label>&nbsp;<input type="password" size="30" id="change_user_pw_newpw" /><br />
        <label class="form_label" for="change_user_pw_newpw_confirm">'.$LANG['index_change_pw_confirmation'].'</label>&nbsp;<input type="password" size="30" id="change_user_pw_newpw_confirm" />
        <span id="show_generated_pw" style="display:none;"><label class="form_label" for="generated_user_pw">'.$LANG['generated_pw'].'</label>&nbsp;<span id="generated_user_pw"></span></span>
    </div>
    <div style="width:100%;height:20px;">
        <div id="pw_strength" style="margin:5px 0 5px 120px;"></div>
    </div>
    <div style="text-align:center;margin-top:8px; display:none;" id="change_user_pw_wait"><img src="includes/images/ajax-loader.gif" /></div>
    <input type="hidden" id="change_user_pw_id" />
</div>';
// DIV FOR CHANGING EMAIL
echo '
<div id="change_user_email" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="change_user_email_error"></div>' .
$LANG['give_new_email'].'
    <div style="font-weight:bold;text-align:center;color:#FF8000;display:inline;" id="change_user_email_show_login"></div>
    <div style="margin-top:10px;text-align:center;">
        <input type="text" size="50" id="change_user_email_newemail" />
    </div>
    <input type="hidden" id="change_user_email_id" />
</div>';
// DIV FOR HELP
echo '
<div id="help_on_users" style="">
    <div>'.$LANG['help_on_users'].'</div>
</div>';
// USER MANAGER
echo '
<div id="manager_dialog" style="display:none;">
    <div style="text-align:center;padding:2px;" class="ui-state-error ui-corner-all" id="manager_dialog_error"></div>
</div>';

/*// MIGRATE PERSONAL ITEMS FROM ADMIN TO A USER
echo '
<div id="migrate_pf_dialog" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;margin-bottom:10px;" class="ui-state-error ui-corner-all" id="migrate_pf_dialog_error"></div>
    <div>
        <label>'.$LANG['migrate_pf_select_to'].'</label>:
        <select id="migrate_pf_to_user">
            <option value="">-- '.$LANG['select'].' --</option>'.$listAvailableUsers.'
        </select>
        <br /><br />
        <label>'.$LANG['migrate_pf_user_salt'].'</label>: <input type="text" id="migrate_pf_user_salt" size="30" /><br />
    </div>
</div>';*/
// USER LOGS
echo '
<div id="user_logs_dialog" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="user_logs"></div>
     <div>' .
$LANG['nb_items_by_page'].':
        <select id="nb_items_by_page" onChange="displayLogs(1,$(\'#activity\').val())">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        &nbsp;&nbsp;' .
$LANG['select'].':
        <select id="activity" onChange="show_user_log($(\'#activity\').val())">
            <option value="user_mngt">'.$LANG['user_mngt'].'</option>
            <option value="user_activity">'.$LANG['user_activity'].'</option>
        </select>
        <span id="span_user_activity_option" style="display:none;">&nbsp;&nbsp;' .
$LANG['activity'].':
            <select id="activity_filter" onChange="displayLogs(1,\'user_activity\')">
                <option value="all">'.$LANG['all'].'</option>
                <option value="at_modification">'.$LANG['at_modification'].'</option>
                <option value="at_creation">'.$LANG['at_creation'].'</option>
                <option value="at_delete">'.$LANG['at_delete'].'</option>
                <option value="at_import">'.$LANG['at_import'].'</option>
                <option value="at_restored">'.$LANG['at_restored'].'</option>
                <option value="at_pw">'.$LANG['at_pw'].'</option>
                <option value="at_shown">'.$LANG['at_shown'].'</option>
            </select>
        </span>
    </div>
    <table width="100%">
        <thead>
            <tr>
                <th width="20%">'.$LANG['date'].'</th>
                <th id="th_url" width="40%">'.$LANG['label'].'</th>
                <th width="20%">'.$LANG['user'].'</th>
                <th width="20%">'.$LANG['activity'].'</th>
            </tr>
        </thead>
        <tbody id="tbody_logs">
        </tbody>
    </table>
    <div id="log_pages" style="margin-top:10px;"></div>
</div>';

// USER EDIT DIALOG
echo '
<div id="user_edit_login_dialog" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="user_edit_login_dialog_message"></div>
    <div>
        <label for="edit_name" class="label_cpm">'.$LANG['name'].'</label>
        <input type="text" id="edit_name" class="input_text text ui-widget-content ui-corner-all" value="" />
        <br />
        <label for="edit_lastname" class="label_cpm">'.$LANG['lastname'].'</label>
        <input type="text" id="edit_lastname" class="input_text text ui-widget-content ui-corner-all" value="" />
        <br />
        <label for="edit_login" class="label_cpm">'.$LANG['login'].'</label>
        <input type="text" id="edit_login" class="input_text text ui-widget-content ui-corner-all" value="" />
    </div>
</div>';