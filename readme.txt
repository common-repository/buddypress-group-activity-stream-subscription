=== BuddyPress Group Activity Notifications ===
Contributors: David Cartwright
Tags: buddypress, activities, groups, emails, notifications
Requires at least: 2.9.1
Stable tag: 1.4

This plugin enables email notifications for group activities within BuddyPress.

== Description ==






!!!!!THIS PLUGIN IS NO LONGER SUPPORTED!!!!!

It is recommended that you instead move to BuddyPress Group Email Subscription:

http://wordpress.org/extend/plugins/buddypress-group-email-subscription/

!!!!!THIS PLUGIN IS NO LONGER SUPPORTED!!!!!













This plugin enables email notifications to all group members whenever a new item is added to the group activity stream. Maximum frequency of notifications is configurable by each user on a group-by-group basis and the backend admin menu allows site admins to configure default settings for this frequency (the plugin normally defaults to 'less spam' rather than more emails).

Group admins and mods can also override the user settings for updates to send out group notifications to all their group users.

1.3 brings support for individual forum topic subscription/unsubscription.  You'll notice additional buttons in the forum area to control this.

To protect against spam, users must have been registered on the site for 3 days before any group updates are emailed to group members. This can be changed in the backend by the site admin.

NOTE: If you plan to use this plugin on a site with a large user base I would recommend evaluating the performance impact of enabling all of these email alerts.

FUTURE DEVELOPMENT: I hope to add the ability for daily digests of updates in some way. Not sure when I'll get a chance to do this though.  Also need to update the styling in various places and clean everything up.  You have been warned :)

NOTE TO PLUGIN AUTHORS: You can hook your own activitity types to reflect specific activities for your plugins.  See the example code below:

`
// Add notification controls for the wiki edits
add_action( 'ass_extra_activities' , 'wiki_group_notifications_add' );
function wiki_group_notifications_add() {
	global $ass_activities;
	
	$ass_activities[] = array("type"=>"new_wiki_edit", "nice_name"=>"edited a wiki page", "default_frequency"=>3600);
	$ass_activities[] = array("type"=>"new_wiki_comment", "nice_name"=>"commented on a wiki page", "default_frequency"=>3600);
}
`

The above code adds support for two new activity types "new_wiki_edit" and "new_wiki_comment", with a default maximum email frequency of 1 per 60 minutes (3600 seconds).  These defaults can then be tweaked in the same way as the default activities (i.e. they will appear in the backend menus).

== Installation ==

1. Install plugin
3. Default notification settings can be configured in the wp-admin backend, under BuddyPress>Group Notifications

== Screenshots ==

1. Member Group Screen (Changing Personal Settings)
2. Admin Group Screen (Admin Notification)
3. Backend Admin (Changing Defaults)
4. Example Email

== Changelog ==

= 1.3 =
Added support for topic-by-topic settings for forum notifications

= 1.2 =
Tagged stable release.
Added Boone Gorges as an author.
Made "do a dance" installation step optional.

= 1.1 =
Fixed directory rename causing white screen of death.

= 1.0 =
Initial release.  Please test and provide feedback.  Not recommended for production sites.

