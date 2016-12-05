<?php

class ZSR_Study_Rooms_Config
{
	/**
	 * General Settings
	 */
	public $domain       = 'your.university.edu';
	public $dir          = '/studyrooms/';
	public $force_ssl    = false;
	public $ssl_required = 'cancel|login|redirect|reservations|reserve';
	public $admin_email  = 'email@university.edu';
	public $admin_name   = '';
	public $email_domain = '@users.university.edu';
	public $full_path    = 'http://your.university.edu';

	/**
	 * MySQL Settings
	 */
	public $mysql = array
	(
		'host'     => '',
		'username' => '',
		'password' => '',
		'dbname'   => array
		(
			'studyrooms' => 'zsr_studyrooms',
			'hours'      => 'zsr_hours'
		)
	);

	/**
	 * LDAP Authentication
	 */
	public $ldap = array
	(
		'host' => '',
		'port' => 389,
		'dn'   => ''
	);

	/**
	 * Mail Settings
	 * @see zsr-studyrooms-mailer.php
	 */
	public $use_phpmailer = false;
	public $inc_phpmailer = '';
	public $smtp = array
	(
		'host'     => 'smtp.gmail.com',
		'auth'     => true,
		'port'     => 587,
		'secure'   => 'tls',
		'username' => '',
		'password' => ''
	);

	/**
	 * Template Settings
	 */
	public $template = array
	(
		'title'                   => 'Study Rooms',
		'tagline'                 => 'Get a room. Study hard.',
		'css'                     => '<link rel="stylesheet" href="studyrooms.css">',
		'js'                      => '<script src="studyrooms.js"></script>',
		'email_subject'           => 'Your Study Room Reservation',
		'reminder_subject'        => 'Reminder: Your Study Room Reservation',
		'header_class'            => '',
		'footer_class'            => '',
		'more_link_text'          => 'About the Library',
		'more_link_url'           => '/about/',
		'reservation_limit'       => '2 hours per day per person',
		'reservation_limit_alert' => '** Important: Reservations may not exceed %s.',
		'welcome'                 => 'Need a place to study? Get a room - here online now. <strong>%s</strong>. Need to change, cancel, or check on a reservation? <a href="%s">Log in</a>.</p>',
		'more_places'             => 'Looking for the auditorium or classrooms in the library? See our <a href="/about/spaces/">Spaces &amp; Places</a> page.',
		'username_label'          => 'Username',
		'password_label'          => 'Password',
		'display_closed'          => 'The library %s closed',
		'display_unavailable'     => 'The library %s closed and our study rooms %s unavailable.',
		'about_page_title'        => 'Policy &amp; Guidelines',
		'display_logout'          => 'Goodbye',
		'studyrooms_options'      => '<span class="lead">Can&#8217;t find a room? Meet online.</span> Try <a href="http://www.google.com/hangouts/">Google Hangouts</a> or <a href="http://www.skype.com/">Skype</a>.',
	);

	/**
	 * Days Allowed
	 * how many days in the future users are allowed to see
	 */
	public $days_allowed = array
	(
		'past' => array
		(
			'default'  => 0,
			'extended' => 100
		),
		'future' => array
		(
			'default'  => 5,
			'extended' => 100
		)
	);

	/**
	 * Day Hour Limit
	 * how many hours users are allowed to reserve per day
	 */
	public $day_hour_limit = array
	(
		'default'  => 2,
		'extended' => 24
	);

	/**
	 * Session Times
	 * how long do user logins last
	 */
	public $session = array
	(
		'default'  => 600, // 600 seconds or 10 minutes (60*10)
		'extended' => 18000 // 18000 seconds or 5 hours (60*60*5)
	);
	
	/**
	 * Hours of Operation
	 * when the building *usually* opens and closes
	 * @see zsr-library-hours.php
	 */
	public $hours_of_operation = array
	(
		'open'  => '12:00AM',
		'close' => '12:00AM'
	);

	/**
	 * Users
	 * admin & extended users are granted special reservation privileges, see above
	 * blocked users are prevented from making reservations
	 */
	public $users = array
	(
		'admin'    => array(),
		'extended' => array(),
		'blocked'  => array()
	);

	/**
	 * Messages
	 * used for specified functions
	 */
	public $messages = array
	(
		'alert' => array(),
		'error' => array
		(
			'login_fail'               => 'Login failed.',
			'requested_date'           => 'This date is outside of the allowed reservation period.', // (or hours for this date are unavailable)
			'bad_reservation_data'     => 'No reservation selected',
			'unauthorized'             => 'Unauthorized',
			'post_process_reservation' => 'Sorry - something&#8217;s wrong and we couldn&#8217;t reserve %s.',
			'update_reservation'       => 'Sorry - we&#8217;re unable to reserve %s.',
			'delete_reservation'       => 'Sorry - we were unable to cancel that reservation.',
			'user_double_booked'       => 'Sorry - we couldn&#8217;t save your reservation. It looks like you&#8217;ve already booked a room at that time.',
			'lacking_time_between'     => 'Sorry - there is too little time between your reservations. They must be at least 1 hour apart.',
			'over_hour_limit'          => 'Sorry - we couldn&#8217;t save your reservation. There&#8217;s a two hour limit and it looks like you&#8217;ve already booked something that day.',
			'check_user_blocked'       => 'Sorry - it appears that you have been blocked. Please contact us for more information.',
			'check_this_reservation'   => 'Sorry - we couldn&#8217;t book that reservation.',
			'check_time_limit'         => 'Sorry - you have exceeded the maximum time we allow per day.',
			'check_time_available'     => 'Sorry - that time is unavailable.',
			'check_reminder'           => 'Sorry - we couldn&#8217;t book that reservation. You indicated you wanted a text reminder but we&#8217;re missing your cell # and/or provider. Please click back and try again.',
		),
		'success' => array
		(
			'update_reservation'       => 'Your reservation has been updated.',
			'add_reservation'          => 'Your reservation has been saved.',
			'delete_reservation'       => 'Your reservation has been canceled.',
			'post_login'               => 'You are now logged in.',
			'display_logout'           => 'You have been logged out.'
		)
	);

	/**
	 * SMS Carriers
	 */
	public $carriers = array
	(
		'Alltel'   => 'message.alltel.com',
		'AT&T'     => 'txt.att.net',
		'Cricket'  => 'mms.mycricket.com',
		'Nextel'   => 'messaging.nextel.com',
		'Sprint'   => 'messaging.sprintpcs.com',
		'T-Mobile' => 'tmomail.net',
		'Verizon'  => 'vtext.com',
		'Virgin'   => 'vmobl.com'
	);
}
