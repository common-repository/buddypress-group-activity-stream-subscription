<?php


// Loads JS on forum pages
function ass_add_js() {
	global $bp;
	
	if ( $bp->current_component == $bp->groups->slug && $bp->current_action == 'forum' ) {
		wp_register_script('bp-activity-subscription-js', WP_PLUGIN_URL . '/buddypress-group-activity-stream-subscription/bp-activity-subscription-js.js');
		wp_enqueue_script( 'bp-activity-subscription-js' );

	}
}
add_action( 'wp_head', 'ass_add_js', 1 );

// Loads required stylesheet on forum pages
function ass_add_css() {
	global $bp;
	if ( $bp->current_component == $bp->groups->slug && $bp->current_action == 'forum' ) {
   		$style_url = WP_PLUGIN_URL . '/buddypress-group-activity-stream-subscription/bp-activity-subscription-css.css';
        $style_file = WP_PLUGIN_DIR . '/buddypress-group-activity-stream-subscription/bp-activity-subscription-css.css';
        if (file_exists($style_file)) {
            wp_register_style('activity-subscription-style', $style_url);
            wp_enqueue_style('activity-subscription-style');
        }
    }
}
add_action( 'wp_print_styles', 'ass_add_css' );




/****************************************************************************************************************
 *	Here we hook the function to email group members to occur after any activity stream update for the group. 	*
 *	This simply does the notify stuff then returns the $params it was passed 								  	*
 ****************************************************************************************************************/
add_action( 'bp_activity_add' , 'ass_notify_group_members' , 50 );
// The actual email notification function
function ass_notify_group_members( $params ) {
	global $bp, $ass_activities;
		
	if ( $params['component'] == 'groups' && $params['type'] != 'created_group' && $params['type'] != 'deleted_wiki_comment' ) {
		$group_id = $params['item_id'];
		$activity_user_id = $bp->loggedin_user->id;
		// Check the age of the registration of the user vs the site setting for minimum registration time
		$ass_reg_age_setting = get_site_option( 'ass_activity_frequency_ass_registered_req' );
		if ( $ass_reg_age_setting != 'n/a' ) {
			$current_user_info = get_userdata( $activity_user_id );
			if ( strtotime(current_time("mysql", 0)) - strtotime($current_user_info->user_registered) < ( $ass_age_setting*24*60*60 ) ) {
				return $params;
			}
		}
		$activity_user_name = $bp->loggedin_user->fullname;
		$activity_link = $params['primary_link'];
		$activity_content = strip_tags(stripslashes($params['content']));
		$activity_type = $params['type'];		
		// Generate a nice description for the update based on the activity type.
		// First set a description based on the default
		$activity_nice_name = $ass_activities[0]['nice_name'];
		// Now change it if this activity type is defined
		foreach ( $ass_activities as $ass_activity ) {
			if ( $ass_activity['type'] == $activity_type ) {
				$activity_nice_name = $ass_activity['nice_name'];
			}
		}
		
		// If it's a forum post, get the topic_id. Outside member loop so you only load $post once
		if ( $params['type'] == 'new_forum_topic' || $params['type'] == 'new_forum_post' ) {
				$post = bp_forums_get_post( $params['secondary_item_id'] );
				$topic_id = $post->topic_id;
		}
		
		$group = new BP_Groups_Group( $group_id, false, true );
		$subject = '[' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ' | ' . $group->name . '] ' . $activity_user_name . ' ' . $activity_nice_name . '.' ;
		
		$user_ids = BP_Groups_Member::get_group_member_ids( $group->id ); 

		foreach ( $user_ids as $user_id ) { 
			// Make sure the user has activity notification settings.  Set default if not.
			ass_user_settings( $user_id );
			// If update was done by the current user we don't bother to email them
			if ( $user_id == $activity_user_id ) continue;
			// Check to see if user has disabled updates for this activity type
			if ( 'no' == get_usermeta( $user_id, 'ass_new_' . $activity_type . '_' . $group_id ) ) continue;
			// Check to see if this update falls within the frequency limit set by user
			$ass_frequency = get_usermeta( $user_id, 'ass_new_' . $activity_type . '_' . $group_id );
			$ass_last_update = groups_get_groupmeta( $group_id, 'ass_new_' . $activity_type . '_' . $user_id  );
			if ( time() - $ass_last_update < $ass_frequency ) continue;
			// If it's a forum post, check to see topic_id is in the mute list
			if ( $params['type'] == 'new_forum_topic' || $params['type'] == 'new_forum_post' ) {
					if ( get_usermeta( $user_id, 'ass_mute' ) ) {
						$mute = get_usermeta( $user_id, 'ass_mute' );
						$mute_list = explode( ',', $mute );
					} else {
						$mute_list = array();
					}					
					
					if ( in_array( $topic_id, $mute_list ) ) {
						continue;
					}
			}
			
			// Set the group timestamp for this activity type and user id
			groups_update_groupmeta( $group_id , 'ass_new_' . $activity_type . '_' . $user_id , time() );
			
			// Get the details for the user
			$ud = bp_core_get_core_userdata( $user_id );

			// Set up and send the message
			$to = $ud->user_email;

			$group_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $group->slug;
			if ( $activity_link == '' ) {
				$activity_link = $group_link;
			}
			$settings_link = $group_link . '/notifications/';

			$message = sprintf( __(
'%s %s.

%s

You can view or respond to this activity by clicking on the link below:
%s

---------------------
', 'buddypress' ), $activity_user_name, $activity_nice_name, $activity_content, $activity_link );


			$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );

			// Send it
			wp_mail( $to, $subject, $message );

			unset( $message, $to );
		}
		
	}
	
	return $params;
}


// Creates "subscribe/unsubscribe" link on forum directory page and each topic page
function ass_subscribe_link( $title = '' ) {
	global $bp;  
	
	$topic_id = bp_get_the_topic_id();
	
	$nonce = wp_create_nonce( 'ass_subscribe' );
	
	$mute = get_usermeta( $bp->loggedin_user->id, 'ass_mute' );
	if ( $mute )
		$mute_list = explode( ',', $mute );
	else
		$mute_list = array();

	if ( in_array( $topic_id, $mute_list ) )
		$link = "<div class='generic-button topic-subscribe'><a href='#' title='Click to subscribe' id=\"subscribe-" . $topic_id . '-' . $nonce . "\">Subscribe</a></div>";
		
		//$link = "<a href='#' class=\"topic-subscribe\" id=\"subscribe-" . $topic_id . '-' . $nonce . "\"><img src=\"" . WP_PLUGIN_URL . "/buddypress-group-activity-stream-subscription/images/unsubscribed.png\" style='background-image: url(images/unsubscribed.png);' /></a>";
	else
		$link = "<div class='generic-button topic-subscribe'><a href='#' title='Click to unsubscribe' id=\"unsubscribe-" . $topic_id . '-' . $nonce . "\">Unsubscribe</a></div>";	
	
	if ( $bp->action_variables[0] == 'topic' )
		return $link . ' ' . $title;
	else if ( $title != '' ) // In order to avoid hooking to bp_get_the_topic_title on group forum page. Todo: Find a better place to hook
		echo $title;
	else
		echo "<td>" . $link . "</td>";
}
add_action( 'bp_directory_forums_extra_cell', 'ass_subscribe_link' );
add_action( 'bp_get_the_topic_title', 'ass_subscribe_link', 99, 1 );



// Handles AJAX request to subscribe/unsubscribe from topic
function ass_ajax_callback() {
	global $bp;
	check_ajax_referer( "ass_subscribe" );
	
	$action = $_POST['a'];
	$user_id = $bp->loggedin_user->id;
	$topic_id = $_POST['topic_id'];
		
	ass_subscribe_unsubscribe( $action, $user_id, $topic_id );
	
	echo $action;
	die();
}
add_action( 'wp_ajax_ass_ajax', 'ass_ajax_callback' );


// Adds/removes a $topic_id from the $user_id's mute list.
function ass_subscribe_unsubscribe( $action, $user_id, $topic_id ) {
	$mute = get_usermeta( $user_id, 'ass_mute' );
	
	if ( $mute )
		$mute_list = explode( ',', $mute );
	else
		$mute_list = array();
	
	if ( $action == 'subscribe' ) {
		if ( in_array( $topic_id, $mute_list ) ) {
			$topic_key = array_keys( $mute_list, $topic_id );
			$key = $topic_key[0];
			unset( $mute_list[$key] );
		}
	} elseif ( $action == 'unsubscribe' ) {
		if ( !in_array( $topic_id, $mute_list ) ) {
			$mute_list[] = $topic_id;
		}
	}
	
	$mute = implode( ',', $mute_list );
	
	update_usermeta( $user_id, 'ass_mute', $mute );
}
























/****************************************************************************************************************
 *	Here we hook into the query_vars parsing so we can get our own form vars									*
 *																											  	*
 ****************************************************************************************************************/
function ass_form_vars($public_query_vars) {
	global $ass_activities;
		
	$public_query_vars[] = 'ass_form';
	$public_query_vars[] = 'ass_admin_notify';
	$public_query_vars[] = 'ass_group_id';
	$public_query_vars[] = 'ass_admin_notice';
	$public_query_vars[] = 'ass_admin_settings';
	$public_query_vars[] = 'ass_registered_req';
	
	foreach ( $ass_activities as $ass_activity ) {
		$public_query_vars[] = $ass_activity['type'];
	}

	return ($public_query_vars);
}
add_filter('query_vars', 'ass_form_vars');



// these are notes - ignore for now
function ass_send_digest( $interval ) {
	global $bp;
	
	if ( !$interval )
		$interval = '1week';
	
	switch ( $interval ) {
		case '1week':
			$secs = 604800;
		break;
	
		case '1day':
			$secs = 86400;
		break;
	
		case '12hour':
			$secs = 43200;
		break;
	
		case '3hour':
			$secs = 10800;
		break;		
		
	}
	
	
	if ( bp_has_groups() ) {
		while ( bp_groups() ) : bp_the_group();
		$group_id = bp_get_group_id();
		
		if ( bp_has_activities( 'display_comments=stream' ) ) {
					global $activities_template;
				
				$time = time();
				
				foreach ( $activities_template->activities as $key=>$activity ) {
					$recorded_time = strtotime( $activity->date_recorded );
					
					if ( $time - $recorded_time < $secs ) {
						$action = str_replace( ': <span class="time-since">%s</span>', ' at ' . date('h:ia \o\n l, F j, Y', $recorded_time) , $activity->action);
						print_r($action);
						print_r( $activity->content );
						echo "<br >";
					}
				}
				
				
				print "<pre>";
				//print_r($activities_template);
				print "</pre>";
		}
		
		endwhile;
	}
}
//add_action('plugins_loaded', 'ass_send_digest');

/* Pseudo-code for cron job */
/*
	$this_interval is the interval for this cron job, ie 1hour
	foreach (groups as group)
		$pref_array = associative groupmeta which has member $interval preference
		
		if ( !in_array( $this_interval$pref_array ) ) // if no one in the group has this interval
			continue; // no need to bother with the rest - go to the next group			
		
		grab and format the activities
		build email content for each $interval
		get group members
		$pref_array = associative groupmeta which has member $interval preference
		foreach (members as member)
			get digest prefs from $pref_array ($user_id as $key)
			if ($pref_array[$user_id] = this $interval ) {			
				get email address (currently how plugin does it - can it be moved out to a single call? at least do it at the end so you only get the email address of the user in question)
				send
			}
		end foreach member
	end foreach group
*/

















/****************************************************************************************************************
 *	Call this at the start of our form processing function to get the variables for use in our script			*
 *																											  	*
 ****************************************************************************************************************/
function ass_get_form_vars(){  
    global $ass_form_vars, $ass_activities;  
	
    if(get_query_var('ass_form')) {  
        $ass_form_vars['ass_form'] = mysql_real_escape_string(get_query_var('ass_form'));  
    }
	
    if(get_query_var('ass_group_id')) {  
        $ass_form_vars['ass_group_id'] = mysql_real_escape_string(get_query_var('ass_group_id'));  
    }
		
    if(get_query_var('ass_admin_notify')) {  
        $ass_form_vars['ass_admin_notify'] = mysql_real_escape_string(get_query_var('ass_admin_notify'));  
	}
	
    if(get_query_var('ass_admin_notice')) {  
        $ass_form_vars['ass_admin_notice'] = mysql_real_escape_string(get_query_var('ass_admin_notice'));  
	}
	
    if(get_query_var('ass_admin_settings')) {  
        $ass_form_vars['ass_admin_settings'] = mysql_real_escape_string(get_query_var('ass_admin_settings'));  
	}
	
    if(get_query_var('ass_registered_req')) {  
        $ass_form_vars['ass_registered_req'] = mysql_real_escape_string(get_query_var('ass_registered_req'));  
	}
	
	foreach ( $ass_activities as $ass_activity ) {
		if(get_query_var($ass_activity['type'])) {  
			$ass_form_vars[$ass_activity['type']] = mysql_real_escape_string(get_query_var($ass_activity['type']));  
		}
	}
	
    return $ass_form_vars;  
} 
//-------------------------------------------------------------------------------------------















/****************************************************************************************************************
 *	This is called before a template_redirect so we can process our form and give the user feedback as needed	*
 *																											  	*
 ****************************************************************************************************************/
function ass_admin_notice() {
    global $bp, $wpdb, $ass_form_vars, $ass_activities;
	
	// Get the form vars
	ass_get_form_vars();  
	
	if ( $ass_form_vars['ass_admin_notice'] && $ass_form_vars['ass_admin_notify'] ) {
		// Check the nonce
		wp_verify_nonce('ass_admin_notice');
		
		// Make the group ID a little easier to read
		$group_id = $ass_form_vars['ass_group_id'];
		
		// Make sure the user is an admin or mod of this group
		if ( !groups_is_user_admin( $bp->loggedin_user->id , $group_id ) && !groups_is_user_mod( $bp->loggedin_user->id , $group_id ) ) {
			// If they're not, tell them so and then 'get the fudge out'
			bp_core_add_message( __( 'You are not allowed to do that.', 'buddypress' ), 'error' );
			bp_core_redirect( $bp->root_domain );
		}
		// Post an update to the group and force sending of an email to all group members
		$activity_user_id = $bp->loggedin_user->id;
		$activity_user_name = $bp->loggedin_user->fullname;
		$activity_link = '';
		$activity_content = strip_tags(stripslashes($ass_form_vars['ass_admin_notice']));
		$activity_type = 'admin_notice';		
		// Generate a nice description for the update based on the activity type.
		// First set a description based on the default
		$activity_nice_name = 'posted an important group update';
		
		$group = new BP_Groups_Group( $group_id, false, true );
		$subject = '[' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ' | ' . $group->name . '] ' . $activity_user_name . ' ' . $activity_nice_name . '.' ;

		$user_ids = BP_Groups_Member::get_group_member_ids( $group->id ); 
		foreach ( $user_ids as $user_id ) { 

			// Get the details for the user
			$ud = bp_core_get_core_userdata( $user_id );

			// Set up and send the message
			$to = $ud->user_email;

			$group_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $group->slug;
			if ( $activity_link == '' ) {
				$activity_link = $group_link;
			}
			$settings_link = $group_link . '/notifications/';

			$message = sprintf( __(
'%s %s.

%s

Please respond to this notice by visiting your group homepage:
%s

---------------------
', 'buddypress' ), $activity_user_name, $activity_nice_name, $activity_content, $activity_link );

			// Send it
			wp_mail( $to, $subject, $message );

			unset( $message, $to );
		}
		bp_core_add_message( __( 'Message sent successfully', 'buddypress' ) );
		bp_core_redirect( wp_get_referer() );
	} 
	// If we get to this point the page request isn't our submitted form.  The rest of WP will load normally now
}
add_action('template_redirect', 'ass_admin_notice');  
//-------------------------------------------------------------------------------------------

























/****************************************************************************************************************
 *	This is called before a template_redirect so we can process our form and give the user feedback as needed	*
 *																											  	*
 ****************************************************************************************************************/
function ass_update_settings() {
    global $bp, $wpdb, $ass_form_vars, $ass_activities;
	
	// Get the form vars
	ass_get_form_vars();  
	
	if ( $ass_form_vars['ass_group_id'] && $ass_form_vars['ass_form'] ) {
		// Check the nonce
		wp_verify_nonce('ass_settings');
		
		// Make the group ID a little easier to read
		$group_id = $ass_form_vars['ass_group_id'];
		
		// Make sure the user is a member of this group
		if ( !groups_is_user_member( $bp->loggedin_user->id , $group_id ) ) {
			// If they're not, tell them so and then 'get the fudge out'
			bp_core_add_message( __( 'You are not allowed to do that.', 'buddypress' ), 'error' );
			bp_core_redirect( $bp->root_domain );
		}
		// Process each of the user setting changes
		foreach ( $ass_form_vars as $key=>$ass_form_var ) {
			// Check to get rid of the group_id and form submit check vars.  Oops.
			if ( $key == 'ass_group_id' || $key == 'ass_form' ) continue;
			
			if ( $ass_form_var > 0 && $ass_form_var < 24*60*60+1 ) {
				update_usermeta( $bp->loggedin_user->id, 'ass_new_' . $key . '_' . $group_id, $ass_form_var );  
			} else {
				update_usermeta( $bp->loggedin_user->id, 'ass_new_' . $key . '_' . $group_id, 'no' );  
			}
		}
		// End of user settings change process
		
		bp_core_add_message( __( 'Settings saved successfully', 'buddypress' ) );
		bp_core_redirect( wp_get_referer() );
	} 
	// If we get to this point the page request isn't our submitted form.  The rest of WP will load normally now
}
add_action('template_redirect', 'ass_update_settings');  
//-------------------------------------------------------------------------------------------
























/****************************************************************************************************************
 *	This one gets the settings for all the current possible activity types for the user_id user					*
 *	If there is nothing, the default is set																	  	*
 ****************************************************************************************************************/
function ass_user_settings( $user_id ) {
	global $bp, $ass_activities;
	
	foreach ( $ass_activities as $ass_activity ) {
		// Get the user meta for this activity type
		$user_setting = get_usermeta( $user_id , 'ass_new_' . $ass_activity['type'] . '_' . $bp->groups->current_group->id );
		// If the user doesn't have a setting, set default
		if ( !$user_setting ) {
			update_usermeta( $user_id , 'ass_new_' . $ass_activity['type'] . '_' . $bp->groups->current_group->id , $ass_activity['default_frequency'] );  
		}
		
	}
	
}



















/****************************************************************************************************************
 *	This returns a table of settings fields for all of the activity types available								*
 *	If there is nothing set for the current user and activity type, applies default 							*
 ****************************************************************************************************************/
function ass_group_notification_settings_fields( ) {
	global $bp, $ass_activities;
	
	ass_user_settings( $bp->loggedin_user->id );

	$output = '<table>';
	$output .= '<th>Activity Type</th><th>Maximum Frequency of Emails</th>';
	
	foreach ( $ass_activities as $ass_activity ) {
		// Get the user meta for this activity type
		$user_setting = get_usermeta( $bp->loggedin_user->id , 'ass_new_' . $ass_activity['type'] . '_' . $bp->groups->current_group->id );
		
		$output .= '<tr>';
		$output .= '<td><div class="activity-subscription-settings-field">';
		$output .= ucfirst($ass_activity['nice_name']); 
		$output .= '</div></td>';
		$output .= '<td class="activity-subscription-radio-cell">';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 1*60 . '"';
		if ($user_setting == 1*60) $output .= 'checked="checked"';
		$output .= '/>1 min';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 15*60 . '"';
		if ($user_setting == 15*60) $output .= 'checked="checked"';
		$output .= '/>15 mins';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 60*60 . '"';
		if ($user_setting == 60*60) $output .= 'checked="checked"';
		$output .= '/>60 mins';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 12*60*60 . '"'; 
		if ($user_setting == 12*60*60) $output .= 'checked="checked"';
		$output .= '/>12 hours';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 24*60*60 . '"'; 
		if ($user_setting == 24*60*60) $output .= 'checked="checked"';
		$output .= '/>24 hours';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="no"';
		if ($user_setting == 'no') $output .= 'checked="checked"';
		$output .= '/>Never';
		$output .= '</td>';
	}	
	
	$output .= '</table>';
	
	return $output;
}


















/****************************************************************************************************************
 *	This function sets up the detault activity types.  This info is used to build meta name, nice name, etc		*
 *	Once things are setup, or if they already are, it sets the plugin constant								  	*
 ****************************************************************************************************************/
function ass_init() {
	global $ass_activities;
	
	if ( !get_site_option( 'ass_activity_frequency_ass_registered_req' ) ) update_site_option( 'ass_activity_frequency_ass_registered_req' , 3 );
	
	$ass_activities = array(	array("type"=>"default", "nice_name"=>"added something new to the group", "default_frequency"=>3600),
								array("type"=>"joined_group", "nice_name"=>"joined the group", "default_frequency"=>3600),
								array("type"=>"new_forum_topic", "nice_name"=>"created a new forum topic", "default_frequency"=>3600),
								array("type"=>"new_forum_post", "nice_name"=>"replied to a forum topic", "default_frequency"=>3600),
								array("type"=>"activity_update", "nice_name"=>"posted an update to the group", "default_frequency"=>3600)
							); 
	// action hook for people to add additional activity types
	do_action('ass_extra_activities');
	// update the default frequency in the array if the default frequency has been changed by site admin
	ass_update_activities();
}


















// Function to allow other plugins to add extra activity types.  Plugins should hook in with a higher priority than ass_update_activities
// ...as this will ensure that the default frequency settings are updated by the main functions
// Also updates the $ass_activities global with any new default frequency settings
function ass_update_activities() {
	global $ass_activities;
	// Update the default frequency settings for each activity
	foreach ( $ass_activities as &$ass_activity ) {
		$ass_activity['default_frequency'] = get_site_option( 'ass_activity_frequency_' . $ass_activity['type'] );
	}
}





// Functions to add the backend admin menu to control changing default settings
add_action('admin_menu', 'ass_admin_menu');
function ass_admin_menu() {
	add_submenu_page( 'bp-general-settings', "Group Notifications", "Group Notifications", 'manage_options', 'ass_admin_options', "ass_admin_options" );
}

function ass_admin_options() {
	?>
	<div class="wrap">
		<h2>Group Notification Settings</h2>

		<form id="ass-admin-settings-form" method="post" action="<?php echo $bp->root_domain; ?>/?ass_admin_settings=1">
		
			<?php wp_nonce_field( 'ass_admin_settings' ); ?>
				
			<table class="form-table">
			<?php echo ass_admin_group_notification_settings_fields(); ?>
			</table>
			<br/>
			<p>To help protect against spam, you may wish to require a user to have been a member of the site for a certain amount of days before any group updates are emailed to the other group members.  By default, this is set to 3 days.  </p>
			Member must be registered for<input type="text" size="1" name="ass_registered_req" value="<?php echo get_site_option( 'ass_activity_frequency_ass_registered_req' ); ?>" style="text-align:center"/>days
			
			<p class="submit">
				<input type="submit" value="Save Settings" id="bp-admin-ass-submit" name="bp-admin-ass-submit" class="button-primary">
			</p>

		</form>
		
	</div>
	<?php
}






function ass_admin_group_notification_settings_fields( ) {
	global $bp, $ass_activities;
	
	$output .= '<th><h3>Activity Type</h3></th><th><h3>Default Frequency of Email Notifications</h3></th>';
	
	foreach ( $ass_activities as $ass_activity ) {
		// Get the user meta for this activity type
		$site_setting = get_site_option( 'ass_activity_frequency_' . $ass_activity['type'] );
		if ( $site_setting == '' ) {
			$site_setting = $ass_activity['default_frequency'];
			update_site_option( 'ass_activity_frequency_' . $ass_activity['type'] , $site_setting );
		}
		$output .= '<tr>';
		$output .= '<td>';
		$output .= ucfirst($ass_activity['nice_name']); 
		$output .= '</td>';
		$output .= '<td>';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 1*60 . '"';
		if ($site_setting == 1*60) $output .= 'checked="checked"';
		$output .= '/>1 min';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 15*60 . '"';
		if ($site_setting == 15*60) $output .= 'checked="checked"';
		$output .= '/>15 mins';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 60*60 . '"';
		if ($site_setting == 60*60) $output .= 'checked="checked"';
		$output .= '/>60 mins';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 12*60*60 . '"'; 
		if ($site_setting == 12*60*60) $output .= 'checked="checked"';
		$output .= '/>12 hours';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="' . 24*60*60 . '"'; 
		if ($site_setting == 24*60*60) $output .= 'checked="checked"';
		$output .= '/>24 hours';
		$output .= '<input type="radio" name="' . $ass_activity['type'] . '" value="no"';
		if ($site_setting == 'no') $output .= 'checked="checked"';
		$output .= '/>Never';
		$output .= '</td></tr>';
	}	
	
	return $output;
}












/****************************************************************************************************************
 *	This is called before a template_redirect so we can process our form and give the user feedback as needed	*
 *																											  	*
 ****************************************************************************************************************/
function ass_update_admin_settings() {
    global $bp, $wpdb, $ass_form_vars, $ass_activities;
	
	// Get the form vars
	ass_get_form_vars();  
	
	if ( $ass_form_vars['ass_admin_settings'] ) {
		// Check the nonce
		wp_verify_nonce('ass_admin_settings');
				
		// Make sure the user is site admin
		if ( !is_site_admin() ) {
			// If they're not, tell them so and then 'get the fudge out'
			bp_core_add_message( __( 'You are not allowed to do that.', 'buddypress' ), 'error' );
			bp_core_redirect( $bp->root_domain );
		}
		
		// Process each of the user setting changes
		foreach ( $ass_form_vars as $key=>$ass_form_var ) {
			// Check to get rid of the ass_admin_settings from fields.  Oops.
			if ( $key == 'ass_admin_settings' ) continue;
			
			// Input field for member age of registration is dealt with slightly differently
			if ( $key == 'ass_registered_req' ) {
				if ( $ass_form_var > 0 ) {
					update_site_option( 'ass_activity_frequency_' . $key , $ass_form_var );
				} else {
					update_site_option( 'ass_activity_frequency_' . $key , 'n/a' );
				}
				continue;
			}
			
			if ( $ass_form_var > 0 && $ass_form_var < 24*60*60+1 ) {
				update_site_option( 'ass_activity_frequency_' . $key , $ass_form_var );
			} else {
				update_site_option( 'ass_activity_frequency_' . $key , 'no' );
			}
		}
		// End of user settings change process
		
		bp_core_add_message( __( 'Settings saved successfully', 'buddypress' ) );
		bp_core_redirect( wp_get_referer() );
	} 
	// If we get to this point the page request isn't our submitted form.  The rest of WP will load normally now
}
add_action('template_redirect', 'ass_update_admin_settings');  
//-------------------------------------------------------------------------------------------




?>