<?php
/*
Plugin Name: Group Activity Subscription
Plugin URI: http://wordpress.org/extend/plugins/buddypress-group-activity-stream-subscription
Description: Emails group members a notification whenever an activity is added to the group activity stream.  Do not use on large user-base installations.
Author: Aekeron, boonebgorges
Version: 1.4 trunk
Author URI: http://www.namoo.co.uk
*/


global $ass_activities;
$ass_activities = 'we are going to put the activities data in here later';

function activitysub_load_buddypress() {
	global $ass_activities;
	if ( function_exists( 'bp_core_setup_globals' ) ) {
		require_once ('bp-activity-subscription-main.php');
		return true;
	}
	/* Get the list of active sitewide plugins */
	$active_sitewide_plugins = maybe_unserialize( get_site_option( 'active_sitewide_plugins' ) );

	if ( !isset( $active_sidewide_plugins['buddypress/bp-loader.php'] ) )
		return false;

	if ( isset( $active_sidewide_plugins['buddypress/bp-loader.php'] ) && !function_exists( 'bp_core_setup_globals' ) ) {
		require_once( WP_PLUGIN_DIR . '/buddypress/bp-loader.php' );
		require_once ('bp-activity-subscription-main.php');
		return true;
	}

	return false;
}

add_action( 'plugins_loaded', 'activitysub_load_buddypress', 1 );
add_action( 'plugins_loaded', 'ass_init', 1 );

/* broken?
function load_activity_subscription_plugin() {
	require_once( WP_PLUGIN_DIR . '/bp-activity-subscription/bp-activity-subscription-main.php' );
}

if ( defined( 'BP_VERSION' ) )
	load_activity_subscription_plugin();
else
	add_action( 'bp_init', 'load_activity_subscription_plugin' );
*/
?>