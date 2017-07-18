<?php

class ZSR_Study_Rooms_Reservation
{
	private $config;
	private $db;
	private $hours;
	private $mailer;
	private $user;

	private $page_assets;
	private $page_title;

	private $active_date;
	private $half_hours_selected;
	private $rdiff;
	private $updated_reservations;

	private $days_allowed_past;
	private $days_allowed_future;
	private $day_hour_limit;

	private $alert;
	private $error;
	private $success;
	private $output;

	function __construct()
	{
		require_once 'zsr-studyrooms-config.php';
		$this->config = new ZSR_Study_Rooms_Config();

		require_once 'zsr-studyrooms-database.php';
		$this->db = new ZSR_Study_Rooms_Database();

		require_once 'zsr-library-hours.php';
		$this->hours = new ZSR_Library_Hours();

		require_once 'zsr-studyrooms-mailer.php';
		$this->mailer = new ZSR_Study_Rooms_Mailer();

		require_once 'zsr-studyrooms-user.php';
		$this->user = new ZSR_Study_Rooms_User();
	}

	/* --------------------------------------------------------------------- */

	public function get_a_room()
	{
		$this->get_uri();
		$this->set_state();
		$this->run_methods();
		$this->net_output();
	}
	
	public function get_assets($type,$glue=false)
	{
		if(!empty($this->page_assets[$type]))
		{
			$assets = $this->page_assets[$type];

			if(!empty($glue) && is_string($glue) && is_array($this->page_assets[$type]))
			{
				$assets = implode($glue,$this->page_assets[$type]);
			}

			return $assets;
		}
	}

	public function get_output()
	{
		return $this->output;
	}

	/* --------------------------------------------------------------------- */

	private function get_uri()
	{
		if(!empty($_SERVER['REQUEST_URI']))
		{
			$request = str_replace($this->config->dir,'',$_SERVER['REQUEST_URI']);
			
			// add trailing slash
			if($request == rtrim($this->config->dir,'/'))
			{
				header('Location: '.$this->config->dir);
				exit;
			}
			
			// force ssl or ssl required
			if($this->config->force_ssl)
			{
				if(empty($_SERVER['HTTPS']))
				{
					header('Location: https://'.$this->config->domain.$this->config->dir.$request);
					exit;
				}
			}
			else
			{
				preg_match('/('.$this->config->ssl_required.')/',$request,$match);
				if(empty($_SERVER['HTTPS']) && !empty($match[1]))
				{
					header('Location: https://'.$this->config->domain.$this->config->dir.$request);
					exit;
				}
			}

			// set display mode
			$_GET['display_mode'] = 'fxs';
			$_GET['do_not_track'] = false;
			if(strpos($request,'exhibit') !== false)
			{
				$_GET['display_mode'] = 'exhibit';
				$_GET['do_not_track'] = true;
			}

			// get match
			if(empty($request) || strpos($request,'today') !== false)
			{
				$_GET['date'] = date('Y-m-d');
				$_GET['action'] = 'view';
				return true;
			}
			preg_match('/(\d{4})\/(\d{2})\/(\d{2})/',$request,$match);
			if(!empty($match[1]) && !empty($match[2]) && !empty($match[3]))
			{
				$_GET['date'] = $match[1].'-'.$match[2].'-'.$match[3];
				$_GET['action'] = 'view';
				
				preg_match('/rooms?\/([0-9a-z]+)/',$request,$match);
				if(!empty($match[1]))
				{
					$_GET['room'] = $match[1];
				}
				return true;
			}
			preg_match('/rooms?\/([0-9a-z]+)/',$request,$match);
			if(!empty($match[1]))
			{
				$_GET['room'] = $match[1];
				$_GET['action'] = 'view';
				
				preg_match('/(\d{4})\/(\d{2})\/(\d{2})/',$request,$match);
				if(!empty($match[1]) && !empty($match[2]) && !empty($match[3]))
				{
					$_GET['date'] = $match[1].'-'.$match[2].'-'.$match[3];
				}
				return true;
			}
			preg_match('/cancel\/([\d:]+)/',$request,$match);
			if(!empty($match[1]))
			{
				$_GET['active_reservation_id'] = $match[1];
				$_GET['action'] = 'cancel';
				return true;
			}
			preg_match('/(reservations?)\/?(\d+)?/',$request,$match);
			if(!empty($match[1]))
			{
				$_GET['aspect'] = $match[1];
				if(!empty($match[2]))
				{
					$_GET['this_reservation_id'] = $match[2];
				}
				$_GET['action'] = 'display';
				return true;
			}
			preg_match('/(about|help|maps)/',$request,$match);
			if(!empty($match[1]))
			{
				$_GET['page'] = $match[1];
				$_GET['action'] = 'open';
				return true;
			}
			preg_match('/([a-z]+)/',$request,$match);
			if(!empty($match[1]))
			{
				$_GET['action'] = $match[1];
				return true;
			}
		}
	}

	private function set_state()
	{
		// page
		$this->page_assets['template'][] = 'studyrooms';
		$this->page_assets['title'] = $this->config->template['title'];
		$this->page_assets['css'][] = $this->config->template['css'];
		$this->page_assets['js'][] = $this->config->template['js'];
		$this->page_assets['dm'] = $this->key('display_mode');
		$this->page_assets['dnt'] = $this->key('do_not_track');
		$this->page_title = $this->config->template['title'];
		
		// reservations
		$this->active_date = $this->key('date') ? $this->key('date') : date('Y-m-d');
		$this->half_hours_selected = 0;
		$this->rdiff = array();
		$this->updated_reservations = array();

		// times
		$this->days_allowed_past = $this->config->days_allowed['past']['default'];
		$this->days_allowed_future = $this->config->days_allowed['future']['default'];
		$this->day_hour_limit = $this->config->day_hour_limit['default'];
		$this->check_time_limits();
		
		// outputs
		$this->alert = array();
		$this->error = array();
		$this->success = array();
		$this->output = '';
	}

	private function run_methods()
	{
		$this->db->connect();

		if(!empty($_POST))
		{
			switch($this->key('submit'))
			{
				case 'cancel':
					$this->post_cancel();
				break;
				case 'login':
					$this->post_login();
				break;
				case 'save':
					$this->post_save();
				break;
			}
			$this->display_messages();
		}

		if(!empty($_GET))
		{
			switch($this->key('action'))
			{
				case 'cancel';
					$this->display_cancel_confirmation();
				break;
				case 'display':
					switch($this->key('aspect'))
					{
						case 'reservation':
							$this->display_reservation();
						break;
						case 'reservations':
							$this->display_reservations();
						break;
					}
				break;
				case 'login':
					$this->check_login_redirect();
					$this->display_login();
				break;
				case 'logout':
					$this->display_logout();
					$this->display_default();
				break;
				case 'open':
					switch($this->key('page'))
					{
						case 'about':
							$this->display_about();
						break;
						case 'help':
							$this->display_help();
						break;
						case 'maps':
							$this->display_maps();
						break;
					}
				break;
				case 'redirect':
					$this->redirect_qdate();
				break;
				case 'reserve':
					switch($this->key('mode'))
					{
						case 'save':
							$this->display_save_confirmation();
						break;
						case 'update':
							$this->display_update_confirmation();
						break;
					}
				break;
				case 'view':
					$this->display_reservation_grid();
				break;
			}
		}

		$this->db->disconnect();
	}

	private function net_output()
	{
		$this->output = '<div class="'.$this->get_page_template().'">'
		.               $this->get_page_header()
		.               '<section class="'.$this->config->template['section_class'].'">'
		.               $this->get_frame_header()
		.               $this->get_date_search()
		.               $this->output
		.               '</section>'
		.               $this->get_page_footer()
		.               '</div>'."\n";
	}

	/* --------------------------------------------------------------------- */

	private function display_reservation_grid()
	{
		$this->set_page_title($this->get_reservation_date($this->active_date));
		
		$this_timestamp = strtotime($this->active_date);
		// we subtract a half hour so the grid shows the most recent half hour
		// why not just ( time() - 30*60 ) ?
		$right_now_timestamp = mktime(date('G'),date('i')-30,0,date('m'),date('d'),date('Y'));
		$past_days_allowed_timestamp = mktime(0,0,0,date('m'),date('d')-$this->days_allowed_past,date('Y'));
		$future_days_allowed_timestamp = mktime(0,0,0,date('m'),date('d')+$this->days_allowed_future-1,date('Y'));
		
		$hours = $this->hours->get_hours($this->active_date,$this->config->hours_of_operation,$this->days_allowed_future);
		
		if(!empty($hours) && $this_timestamp >= $past_days_allowed_timestamp && $this_timestamp <= $future_days_allowed_timestamp)
		{
			// init containers
			$room_info_container = '';
			$available_times_container  = '';
			$reservation_grid_container = '';
			
			// || time() > $hours[''] ?
			if(strtolower($hours['text']) != 'closed')
			{
				// init
				$room_info = '';
				$reservation_grid = '';
				$reservation_list = '';
				$available_times = '';
				
				// reservations
				$reserved = $this->rerun_parsed_reservations($this->db->get_reservations_by_date($this->active_date));
				$user_has_reservations = false;
				$active_reservation_id = array();

				// rooms
				if($this->key('room'))
				{
					$rooms = $this->db->get_rooms_by_name($this->key('room'),$this->active_date);
				}
				else
				{
					$rooms = $this->db->get_rooms_by_date($this->active_date);
				}

				$this->page_assets['template'][] = 'n-rms-'.count($rooms);
				
				foreach($rooms as $room)
				{
					// misc
					$n = (strtotime(date('Y-m-d ga',$hours['open'])) == $hours['open'] - 30*60) ? ' even' : ' odd';
					$open_24_hours = (stripos($hours['text'],'Open 24 Hours') !== false) ? true : false;
					$view = ($open_24_hours) ? ' day' : '';
					$class = ' open';
					$extra = '';
					$booked = '';
					$data_open = 0;
					$available_times = '';
					$reservation_cells = '';
					$previous_reservation_id = '';
					$reservation_color_code = mt_rand(1,7);
					
					for($i = $hours['open']; $i < $hours['close']; $i += (30*60))
					{
						$time = date('g:i A',$i);
						$time_id = date('gia',$i);
						$break = '';
						if($open_24_hours)
						{
							if($time == '12:00 AM')
							{
								$time = 'Midnight';
								$view = ' night';
							}
							if($time == '8:00 AM')
							{
								$view = ' day';
								$break = ' break';
							}
							if($time == '12:00 PM')
							{
								$time = 'Noon';
							}
							if($time == '5:00 PM')
							{
								$view = ' night';
								$break = ' break';
							}
						}
						else
						{
							if($time == '12:00 AM')
							{
								$time = 'Midnight';
							}
							if($time == '12:00 PM')
							{
								$time = 'Noon';
							}
						}
						
						if($i > $right_now_timestamp || $this->user->is_extended())
						{
							$available_times_class = $view.$break;
							
							if(!empty($reserved) && isset($reserved[$room['room_id']]) && in_array($i,$reserved[$room['room_id']]))
							{
								if(!empty($this->rdiff[$room['room_id'].'-'.$i]['username']) && !empty($this->rdiff[$room['room_id'].'-'.$i]['reservation_id']))
								{
									$extra = '';
									$booked = '';
									if($this->user->is_logged_in() && $this->rdiff[$room['room_id'].'-'.$i]['username'] == $this->user->username)
									{
										$extra = ' current_user_reservations';
										$user_has_reservations = true;
										$booked = '<input type="checkbox" name="srr-'.$room['room_id'].'-'.$i.'" id="srr-'.$room['room_id'].'-'.$i.'" value="'.$this->rdiff[$room['room_id'].'-'.$i]['reservation_id'].'" checked="checked">';
										$booked .= '<label for="srr-'.$room['room_id'].'-'.$i.'"><em>Reserved:</em> <span class="room-name">'.$room['room_name'].'</span> <span class="time-slot">'.date('g:i A',$i).'</span></label> ';
										// $booked .= '<a class="session" href="'.$this->set_url($this->config->dir.'reservation/'.$this->rdiff[$room['room_id'].'-'.$i]['reservation_id']).'" title="See more info about this reservation">#</a>';
										$active_reservation_id[] = $this->rdiff[$room['room_id'].'-'.$i]['reservation_id'];
									}
									else
									{
										$reservation_color_code = ($previous_reservation_id == $this->rdiff[$room['room_id'].'-'.$i]['reservation_id']) ? $reservation_color_code : ((($reservation_color_code + 1) > 7) ? 1 : ($reservation_color_code + 1));
										$extra = ' d-'.$reservation_color_code;
										$booked = '<div class="label"><span class="room-name">'.$room['room_name'].'</span> <span class="time-slot">'.date('g:i A',$i).'</span> <em>Reserved</em></div> ';
										if($this->user->is_logged_in() && $this->user->is_admin())
										{
											if(!empty($this->rdiff[$room['room_id'].'-'.$i]['session_id']))
											{
												$booked .= '<a class="session" href="'.$this->set_url($this->config->dir.'reservation/'.$this->rdiff[$room['room_id'].'-'.$i]['reservation_id']).'" title="See more info about this reservation">@</a>';
											}
											$booked .= '<strong class="username">'.$this->rdiff[$room['room_id'].'-'.$i]['username'].'</strong>';
										}
									}
									$previous_reservation_id = $this->rdiff[$room['room_id'].'-'.$i]['reservation_id'];
								}
								$class = ' reserved'.$extra.$view.$break;
							}
							else
							{
								$class = ' open'.$view.$break;
								$data_open++;
							}
						}
						else
						{
							$available_times_class = ' past';
							$class = ' past';
						}
						
						$reservation_cells .= '<dd class="cell'.$n.$class.'">';
						if(strpos($class,'past') !== false)
						{
							$reservation_cells .= '<em>unavailable</em>';
						}
						elseif(strpos($class,'open') !== false)
						{
							$reservation_cells .= '<input type="checkbox" name="srr-'.$room['room_id'].'-'.$i.'" id="srr-'.$room['room_id'].'-'.$i.'" value="Y">';
							$reservation_cells .= '<label for="srr-'.$room['room_id'].'-'.$i.'"><span class="room-name">'.$room['room_name'].'</span> <span class="time-slot">'.date('g:i A',$i).'</span></label>';
						}
						else
						{
							$reservation_cells .= $booked;
						}
						$reservation_cells .= '<i class="drag-handle">&nbsp;</i></dd>';
						
						$available_times .= '<li class="'.trim($n.$available_times_class).'"><a id="time_'.$time_id.'" href="#time_'.$time_id.'">'.$time.'</a></li>';
						
						$n = ($n == ' odd') ? ' even' : ' odd';
					}

					// this room
					$this_room_id = str_replace(' ','-',strtolower($room['room_name']));
					// $this_room_name = (count($rooms) > 7) ? str_replace('Room ','',$room['room_name']) : $room['room_name'];
					$this_room_name = str_replace('Room ','',$room['room_name']);
					$this_room_info = 'Location: '.$room['location'].' | Capacity: '.$room['capacity'].' | Equipment: '.$room['equipment'];
					$this_room_data = ($data_open*.5). 'h available';
					$this_room_link = '<a href="#'.$this_room_id.'" title="'.$this_room_info.'" data-availability="'.$this_room_data.'">'.$this_room_name.'</a>';
					
					$reservation_list .= '<dl id="'.$this_room_id.'" class="srs-studyroom flex-item">';
					$reservation_list .= '<dt>'.$this_room_link.'</dt>';
					$reservation_list .= $reservation_cells;
					$reservation_list .= '</dl>';
				}

				$active_reservation_id = array_unique($active_reservation_id);
				
				// available times container
				$available_times_container .= '<ul class="srs-times flex-item">';
				$available_times_container .= $available_times;
				$available_times_container .= '</ul>';

				$time_diff = $this->get_time_diff($hours['open'],$hours['close']);
				if(!empty($time_diff))
				{
					$time_diff = ' class="td-'.$time_diff.'"';
				}
				
				// reservation grid container
				$reservation_grid_container .= '<form id="study_room_reservations"'.$time_diff.' action="https://'.$this->config->domain.$this->config->dir.'reserve" method="post">';
				$reservation_grid_container .= '<div class="srs-rgrid flex-container">';
				$reservation_grid_container .= $available_times_container;
				$reservation_grid_container .= $reservation_list;
				$reservation_grid_container .= '</div>';
				$reservation_grid_container .= '<div class="submit">';
				$reservation_grid_container .= (!empty($active_reservation_id)) ? '<input type="hidden" name="active_reservation_id" value="'.implode(':',$active_reservation_id).'">' : '';
				$reservation_grid_container .= '<input type="hidden" name="open" value="'.$hours['open'].'">';
				$reservation_grid_container .= '<input type="hidden" name="close" value="'.$hours['close'].'">';
				if($user_has_reservations)
				{
					$reservation_grid_container .= '<button type="submit" name="mode" id="update" value="update">Save changes</button> ';
					$reservation_grid_container .= '<p class="cancel">or <a href="'.$this->set_url($this->config->dir.'cancel/'.implode(':',$active_reservation_id)).'">cancel this reservation</a></p>';
				}
				else
				{
					$reservation_grid_container .= '<button type="submit" name="mode" id="save" value="save">Reserve</button> ';
				}
				$reservation_grid_container .= '</div>';
				$reservation_grid_container .= '</form>';
			}
			else
			{
				$this->display_closed();
			}
			
			$this->output .= $room_info_container;
			$this->output .= $reservation_grid_container;
		}
		else
		{
			$this->display_error_message($this->config->messages['error']['requested_date']);
		}
	}

	private function display_closed()
	{
		$s_verb = 'will be';
		$p_verb = $s_verb;

		if($this->is_today($this->active_date))
		{
			$s_verb = 'is';
			$p_verb = 'are';
		}

		$this->output .= '<div class="attention">'
		.                '<h3>'.sprintf($this->config->template['display_closed'],$s_verb).'</h3>'
		.                '<p>'.sprintf($this->config->template['display_unavailable'],$s_verb,$p_verb).'</p>'
		.                '</div>';
	}

	private function display_cancel_confirmation()
	{
		if($this->user->is_logged_in())
		{
			$this->set_page_title('To Cancel a Reservation...');

			if($this->key('active_reservation_id'))
			{
				$this->output .= '<p class="attention">Are you sure you want to cancel this reservation?</p>'
				.                '<form class="srs-reservation cancel standard" action="https://'.$this->config->domain.$this->config->dir.'reservations" method="post">';
				
				$this->display_login_embed();

				$active_id = explode(':',$this->key('active_reservation_id'));
				
				foreach($active_id as $reservation_id)
				{
					$this->display_get_reservation($reservation_id);
				}
				
				$this->output .= '<fieldset class="submit">'
				.                '<input type="hidden" name="active_reservation_id" value="'.$this->key('active_reservation_id').'">'
				.                '<button type="submit" name="submit" id="submit" value="cancel">Yes, cancel this reservation</button>'
				.                '</fieldset>'
				.                '</form>';
			}
		}
		else
		{
			$this->display_login();
		}
	}

	private function display_save_confirmation()
	{
		$this->set_page_title('Reserve &#8250;');
		$this->display_reservation_confirmation();
	}

	private function display_update_confirmation()
	{
		$this->set_page_title('Save Changes &#8250;');
		$this->display_reservation_confirmation();
	}

	private function display_reservation_confirmation()
	{
		$i = 1;
		$reservations = $this->parse_reservations($this->get_selected_reservations());

		if(!empty($reservations))
		{
			$this->output .= '<form class="srs-reservation reserve standard" action="https://'.$this->config->domain.$this->config->dir.'reservations" method="post">';
			
			$this->display_login_embed();

			$carrier_option = array();
			$carrier = array_keys($this->config->carriers);
			foreach($carrier as $carrier_name)
			{
				$carrier_key = strtolower($carrier_name);
				$carrier_key = preg_replace('/[^a-z]/','',$carrier_key);

				$carrier_option[$carrier_key] = $carrier_name;
			}

			foreach($reservations as $r)
			{
				$session = '';
				$session_subject = '';
				$session_notes = '';

				$reminder = '';
				$checked = ' checked="checked"';
				$none_checked = $checked;
				$email_checked = '';
				$txt_checked = '';
				$cell_number = '';
				$provider = '';

				// get session & reminder & user_meta
				// via reservation_id
				if(!empty($r['reservation_id']))
				{
					$session = $this->db->get_reservation_session($r['reservation_id']);
					$session_subject = !empty($session['subject']) ? htmlentities($session['subject']) : '';
					$session_notes = !empty($session['notes']) ? htmlentities($session['notes']) : '';

					$reminder = $this->db->get_reservation_reminder($r['reservation_id']);
					if(!empty($reminder))
					{
						$none_checked = '';

						if($reminder == 'email')
						{
							$email_checked = $checked;
						}
						if($reminder == 'txt')
						{
							$txt_checked = $checked;

							if($this->user->is_logged_in())
							{
								$cell_number = $this->db->get_user_meta_value($this->user->username,'cell_number');
								$provider = $this->db->get_user_meta_value($this->user->username,'provider');
							}
						}
					}
				}
				
				// display reservation
				// display_post_reservation does not display session subject & notes
				// because session subject & notes either do not exist yet
				// or because they will be displayed as form elements
				$this->display_post_reservation($r);

				// session
				$this->output .= '<fieldset class="srs-reservation-session">'
				.                '<div>'
				.                '<label for="subject'.$i.'">Subject</label> '
				.                '<input type="text" name="subject'.$i.'" id="subject'.$i.'" value="'.$session_subject.'">'
				.                '</div>'
				.                '<div>'
				.                '<label for="notes'.$i.'">Notes</label> '
				.                '<textarea name="notes'.$i.'" id="notes'.$i.'" cols="33" rows="2">'.$session_notes.'</textarea>'
				.                '</div>'
				// reminder
				.                '<div>'
				.                '<label>Reminder</label> '
				.                '<div class="radio">'
				.                '<input type="radio" name="reminder'.$i.'" id="reminder-txt'.$i.'" value="txt"'.$txt_checked.'> <label for="reminder-txt'.$i.'">Text</label>&nbsp;&nbsp;'
				.                '<input type="radio" name="reminder'.$i.'" id="reminder-eml'.$i.'" value="email"'.$email_checked.'> <label for="reminder-eml'.$i.'">Email</label>&nbsp;&nbsp;'
				.                '<input type="radio" name="reminder'.$i.'" id="reminder-none'.$i.'" value=""'.$none_checked.'> <label for="reminder-none'.$i.'">None</label>'
				.                '</div>'
				.                '</div>'
				// cell
				.                '<div id="txt'.$i.'" class="txt hide">'
				.                '<div>'
				.                '<label for="cell_number'.$i.'">Cell # <span class="note">(with area code)</span></label> '
				.                '<input type="text" name="cell_number'.$i.'" id="cell_number'.$i.'" value="'.$cell_number.'">'
				.                '</div>'
				// provider
				.                '<div>'
				.                '<label for="cell_provider'.$i.'">Provider</label>'
				.                '<select name="cell_provider'.$i.'" id="cell_provider'.$i.'">'
				.                '<option value="">- -</option>';
				foreach($carrier_option as $carrier_key => $carrier_name)
				{
					$carrier_selected = '';
					if($provider == $carrier_key)
					{
						$carrier_selected = ' selected="selected"';
					}
					$this->output .= '<option value="'.$carrier_key.'"'.$carrier_selected.'>'.$carrier_name.'</option>';
				}
				$this->output .= '</select>'
				.                '</div>'
				.                '</div>'
				.                '</fieldset>';
				
				$i++;
			}
		
			// submit
			$this->output .= '<fieldset class="submit">'
			.                '<input type="hidden" name="active_reservation_id" value="'.$this->key('active_reservation_id').'">';
			foreach($_POST as $k => $v)
			{
				if(strpos($k,'srr-') !== false)
				{
					$this->output .= '<input type="hidden" name="'.$k.'" value="'.$v.'">';
				}
			}
			$this->output .= '<input type="hidden" name="open" value="'.$this->key('open').'">'
			.                '<input type="hidden" name="close" value="'.$this->key('close').'">'
			.                '<input type="hidden" name="js-rcount" id="js-rcount" value="'.($i-1).'">'
			.                '<button type="submit" name="submit" id="submit" value="save">Save my reservation</button>'
			.                '</fieldset>'
			.                '</form>';
		}
		else
		{
			$this->display_error_message($this->config->messages['error']['bad_reservation_data']);
		}
	}

	private function display_reservation()
	{
		$this->display_get_reservation($this->key('this_reservation_id'));
	}

	// @todo remove duplication with display_get_reservation
	private function display_post_reservation($reservation)
	{
		$reservation['start_timestamp'] = (isset($reservation['start_datetime'])) ? strtotime($reservation['start_datetime']) : $reservation['start_timestamp'];
		$reservation['end_timestamp'] = (isset($reservation['end_datetime'])) ? strtotime($reservation['end_datetime']) : $reservation['end_timestamp'];
		
		$room = $this->db->get_room($reservation['room_id']);
		
		$this->output .= '<div id="this_reservation" class="srs-reservation-info changing">'
		.                '<h3>Reservation Details</h3>'
		.                '<dl class="srs-reservation-details">'
		.                '<dt>Location</dt>'
		.                '<dd>'.$room['room_name'].' ('.$room['location'].')</dd>'
		.                '<dt>Date</dt>'
		.                '<dd>'.date('l, F j, Y',$reservation['start_timestamp']).'</dd>'
		.                '<dt>Time</dt>'
		.                '<dd>'.date('g:i A',$reservation['start_timestamp']).' - '.date('g:i A',$reservation['end_timestamp']).'</dd>'
		.                '</dl>'
		.                '</div>';
	}

	private function display_get_reservation($reservation_id)
	{
		if(!empty($reservation_id))
		{
			$reservation = $this->db->get_reservation($reservation_id);

			if(!empty($reservation) && ($this->user->username == $reservation['username'] || $this->user->is_admin()))
			{
				$reservation['start_timestamp'] = strtotime($reservation['start_datetime']);
				$reservation['end_timestamp'] = strtotime($reservation['end_datetime']);
				
				$room = $this->db->get_room($reservation['room_id']);

				$this->set_page_title('Reservation #'.$reservation_id);
				if($this->user->username == $reservation['username'])
				{
					$this->set_page_title('Your Reservation');
				}

				$state = 'static';
				if($this->key('action') == 'cancel')
				{
					$state = 'changing';
				}
				
				$this->output .= '<div id="this_reservation" class="srs-reservation-info '.$state.'">'
				.                '<h3>Reservation Details</h3>'
				.                '<dl class="srs-reservation-details">'
				.                '<dt>User</dt>'
				.                '<dd>'.$reservation['username'].'</dd>'
				.                '<dt>Location</dt>'
				.                '<dd>'.$room['room_name'].' ('.$room['location'].')</dd>'
				.                '<dt>Date</dt>'
				.                '<dd>'.date('l, F j, Y',$reservation['start_timestamp']).'</dd>'
				.                '<dt>Time</dt>'
				.                '<dd>'.date('g:i A',$reservation['start_timestamp']).' - '.date('g:i A',$reservation['end_timestamp']).'</dd>';
				
				if(!empty($reservation['session_id']))
				{
					$session = $this->db->get_session($reservation['session_id']);
					
					if(!empty($session['subject']))
					{
						$this->output .= '<dt>Subject</dt>'
						.                '<dd>'.$session['subject'].'</dd>';
					}
					if(!empty($session['notes']))
					{
						$this->output .= '<dt>Notes</dt>'
						.                '<dd>'.$session['notes'].'</dd>';
					}
				}
				
				$this->output .= '</dl>';

				if($this->user->is_admin() && $this->key('action') != 'cancel' && time() < $reservation['start_timestamp'])
				{
					// @todo <em>Note: The user will be notified of the cancellation.</em>
					$this->output .= '<div class="srs-admin-options">'
					.                '<h4>Admin Options</h4>'
					.                '<p>Need to cancel this reservation? <a href="'.$this->set_url($this->config->dir.'cancel/'.$reservation_id).'">Cancel this reservation</a> &#8250;</p>'
					.                '</div>';
				}
				
				$this->output .= '</div>';
			}
			else
			{
				$this->display_error_message($this->config->messages['error']['unauthorized']);
			}
		}
		else
		{
			$this->display_default();
		}
	}

	private function display_login()
	{
		$this->set_page_title('Login');
		
		$referer = '';
		if($this->key('referer'))
		{
			$referer = $this->key('referer');
		}
		if(empty($referer))
		{
			if(!empty($_SERVER['HTTP_REFERER']))
			{
				$referer = $_SERVER['HTTP_REFERER'];
			}
			else
			{
				$http = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
				$referer = $http.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			}
		}

		$this->output .= '<form class="srs-reservation login standard" action="https://'.$this->config->domain.$this->config->dir.'login" method="post">'
		.                '<fieldset class="login_set solo">'
		.                $this->get_username_field()
		.                $this->get_password_field()
		.                '</fieldset>'
		.                '<fieldset class="submit">'
		.                '<input type="hidden" name="referer" value="'.$referer.'">'
		.                '<button type="submit" name="submit" id="submit" value="login">Log in</button>'
		.                '</fieldset>'
		.                '</form>';
	}

	private function get_login_link()
	{
		$login_link = '<a href="'.$this->set_url($this->config->dir.'login').'">Log in</a> to see your reservations.';

		return $login_link;
	}

	private function display_login_embed()
	{
		if($this->user->is_logged_in())
		{
			$this->output .= '<fieldset class="hidden">'
			.                '<input type="hidden" name="username" value="'.$this->user->username.'">'
			.                '</fieldset>';
		}
		else
		{
			$this->output .= '<fieldset class="login_set">'
			.                $this->get_username_field()
			.                $this->get_password_field()
			.                '</fieldset>';
		}
	}

	private function get_username_field()
	{
		$username = '<div>'
		.           '<label for="username">'.$this->config->template['username_label'].' <em>*</em></label> '
		.           '<input type="text" name="username" id="username" value="" required>'
		.           '</div>';

		return $username;
	}

	private function get_password_field()
	{
		$password = '<div>'
		.           '<label for="password">'.$this->config->template['password_label'].' <em>*</em></label> '
		.           '<input type="password" name="password" id="password" value="" required>'
		.           '</div>';

		return $password;
	}

	private function display_default()
	{
		if($this->user->is_logged_in())
		{
			$this->display_reservations();
		}
		else
		{
			$this->display_welcome();
			$this->display_how_to_steps();
		}
	}

	private function display_welcome()
	{
		$this->output .= '<p>'.sprintf($this->config->template['welcome'],$this->config->template['reservation_limit'],$this->set_url($this->config->dir.'login')).'</p>';
	}

	private function display_help()
	{
		$this->set_page_title('Need Some Help?');
		$this->display_reservation_limit();
		$this->display_how_to_steps();
		$this->display_how_do_i();
		$this->display_more_places();
	}

	private function display_maps()
	{
		$this->set_page_title('Rooms &amp; Locations');

		$this->output .= '<div class="srs-floor-maps">';

		$rooms = $this->db->get_rooms_by_date($this->active_date);

		foreach($rooms as $room)
		{
			$room_id = str_replace(' ','-',strtolower($room['room_name']));
			$room_image = '';
			if(!empty($room['image_url']))
			{
				$room_image = '<img src="'.$room['image_url'].'" alt="Floor map showing '.$room['room_name'].'">';
				if(!empty($room['info_url']))
				{
					$room_image = '<a href="'.$room['info_url'].'">'.$room_image.'</a>';
				}
			}

			$this->output .= '<div class="srs-floor-map">'
			.                $room_image
			.                '<h3 id="'.$room_id.'">'.$room['room_name'].'</h3>'
			.                '<dl class="room-info">'
			.                '<dt>Location: </dt><dd>'.$room['location'].'</dd>'
			.                '<dt>Capacity: </dt><dd>'.$room['capacity'].'</dd>'
			.                '<dt>Equipment: </dt><dd>'.$room['equipment'].'</dd>'
			.                '</dl>'
			.                '</div>';
		}

		$this->output .= '</div>';
	}

	private function display_reservation_limit()
	{
		$this->output .= '<p class="attention"><strong>'.sprintf($this->config->template['reservation_limit_alert'],$this->config->template['reservation_limit']).'</strong></p>';
	}

	private function display_how_to_steps()
	{
		$this->output .= '<ol class="srs-how-to">'
		.                '<li id="schedule">'
		.                '<h3>Choose a day &#8250;</h3>'
		.                $this->get_date_menu('day_of_week')
		.                '<p class="login">Need to change, cancel, or check on a reservation? <a href="'.$this->set_url($this->config->dir.'login').'">Log in</a>.</p>'
		.                '</li>'
		.                '<li id="selecting">'
		.                '<h3>Choose a time &#8250;</h3>'
		.                '<ol>'
		.                '<li>Click, hold, and drag to <span class="highlight">highlight</span> your selection.</li>'
		.                '<li>Click <strong class="reserve">Reserve</strong> to make your reservation.</li>'
		.                '</ol>'
		.                '</li>'
		.                '<li id="confirming">'
		.                '<h3>Get a room.</h3>'
		.                '<ol>'
		.                '<li>Fill out the form.</li>'
		.                '<li>Click <strong class="save">Save</strong>.</li>'
		.                '</ol>'
		.                '</li>'
		.                '</ol>';
	}

	private function display_how_do_i()
	{
		$this->output .= file_get_contents('pages/how-do-i.html');
	}

	private function display_more_places()
	{
		$this->output .= '<p class="prompt">'.$this->config->template['more_places'].'</p>';
	}

	private function display_about()
	{
		$this->set_page_title($this->config->template['about_page_title']);

		$this->output .= file_get_contents('pages/about.html');

		$this->display_more_places();
	}

	// @todo add ability to show other user reservations for admin
	private function display_reservations()
	{
		$this->set_page_title('Your Reservations');
		
		$user_reservations = $this->db->get_user_reservations($this->user->username);

		if(!empty($user_reservations))
		{
			$this->output .= '<div class="srs-upcoming-reservations">'
			.                '<h3>Your Upcoming Reservations</h3>'
			.                '<ul>';
			foreach($user_reservations as $reservation)
			{
				$this->output .= '<li>'.$this->build_reservation_link($reservation).'</li>';
			}
			$this->output .= '</ul>'
			.                '</div>';
		}
		else
		{
			if($this->user->is_logged_in())
			{
				$this->output .= '<p>You have no upcoming reservations.</p>';
			}
			else
			{
				$this->output .= '<p>Log in to see your reservations:</p>';
				$this->display_login();
			}
		}
	}

	private function display_logout()
	{
		$this->set_page_title($this->config->template['display_logout']);
		
		if($this->user->is_logged_in() && $this->user->clear_login())
		{
			$this->display_success_message($this->config->messages['success']['display_logout']);
		}
	}

	private function display_alert_message($message)
	{
		if(!empty($message))
		{
			$this->output .= '<p class="status-alert">'.$message.'</p>';
		}
	}

	private function display_error_message($message)
	{
		if(!empty($message))
		{
			$this->output .= '<p class="status-error">'.$message.'</p>';
		}
	}

	private function display_success_message($message)
	{
		if(!empty($message))
		{
			$this->output .= '<p class="status-success">'.$message.'</p>';
		}
	}

	private function display_messages()
	{
		if(!empty($this->success))
		{
			$this->success = array_unique($this->success);
			foreach($this->success as $s)
			{
				$this->display_success_message($s);
			}
		}
		if(!empty($this->alert))
		{
			$this->alert = array_unique($this->alert);
			foreach($this->alert as $a)
			{
				$this->display_alert_message($a);
			}
		}
		if(!empty($this->error))
		{
			$this->error = array_unique($this->error);
			foreach($this->error as $e)
			{
				$this->display_error_message($e);
			}
		}
	}

	private function get_frame_header()
	{
		$hours = '';
		if($this->key('action') == 'view')
		{
			 $hours = ' <span class="srs-time">'.$this->hours->get_open_hours($this->active_date,$this->config->hours_of_operation,$this->days_allowed_future).'</span>';
		}

		$frame_header = '<header class="srs-frame">'
		.               '<h2 class="srs-model"><span class="srs-location">'.$this->page_title.'</span>'.$hours.'</h2>'
		.               $this->get_date_menu()
		.               '</header>';

		return $frame_header;
	}

	private function get_date_menu($flag='')
	{
		$date_nav = '<ul class="srs-date-menu">';
		for($i = 0; $i <= $this->config->days_allowed['future']['default']-1; $i++)
		{
			$c = '';
			$d = mktime(0,0,0,date('m'),date('d')+$i,date('Y'));
			$dow = date('D',$d);
			$ymd = date('Y/m/d',$d);
			
			if(strtotime($this->active_date) == strtotime($ymd) && $this->key('action') == 'view')
			{
				$c = ' class="srs-current"';
			}
			if($i == 0)
			{
				$dow = 'Today';
			}
			if($flag == 'day_of_week')
			{
				$dow = '<strong>'.date('l',$d).'</strong>, '.date('n/j',$d);
			}
			
			$date_nav .= '<li'.$c.'><a href="'.$this->set_url($this->config->dir.$ymd).'">'.$dow.'</a></li>';
		}
		$date_nav .= '</ul>';

		return $date_nav;
	}

	private function get_date_search()
	{
		$date_search = '';

		if($this->user->is_extended())
		{
			$d = mktime(0,0,0,date('m')+3,date('d')+13,date('Y'));
			$mdy = date('m/d/Y',$d);

			$date_search .= '<div class="srs-date-search">'
			.               '<form action="https://'.$this->config->domain.$this->config->dir.'redirect" method="post">'
			.               '<fieldset>'
			.               '<label for="qdate">Go to a specific date: </label>'
			.               '<input type="text" id="qdate" name="qdate" placeholder="e.g. '.$mdy.'">'
			.               '</fieldset>'
			.               '</form>'
			.               '</div>';
		}

		return $date_search;
	}

	private function get_page_template()
	{
		$template_class = implode(' ',array_filter($this->page_assets['template']));

		return $template_class;
	}

	private function get_page_header()
	{
		$header = '';
		$greeting = '';
		$loginout = '<li class="srs-login"><a href="'.$this->set_url($this->config->dir.'login').'">Login</a></li>';

		if($this->user->is_logged_in())
		{
			$user_firstname = $this->user->get_display_name('firstname');

			$greeting = '<li class="srs-welcome">Hi '.$user_firstname.'</li>';
			if($this->user->is_admin())
			{
				$greeting = '<li class="srs-welcome">Hi <abbr title="Note: Your account has admin privileges">'.$user_firstname.'</abbr></li>';
			}
			if($this->user->is_extended())
			{
				$greeting = '<li class="srs-welcome">Hi <abbr title="Note: Your account has extended privileges">'.$user_firstname.'</abbr></li>';
			}

			$loginout = '<li><a href="'.$this->set_url($this->config->dir.'logout').'">Logout</a></li>';
		}

		$header .= '<header class="'.$this->config->template['header_class'].'">'
		.          '<h1><a href="'.$this->set_url($this->config->dir).'">'.$this->config->template['title'].'</a></h1>'
		.          '<nav>'
		.          '<ul class="srs-menu">'
		.          $greeting
		.          '<li><a href="'.$this->set_url($this->config->dir.'reservations').'">Reservations</a></li>'
		.          '<li><a href="'.$this->set_url($this->config->dir.'maps').'">Locations</a></li>'
		.          $loginout
		.          '</ul>'
		.          '</nav>'
		.          '</header>';
		
		return $header;
	}

	private function get_page_footer()
	{
		$footer = '<footer class="srs-support '.$this->config->template['footer_class'].'">'
		.         '<p class="srs-tagline">'.$this->config->template['tagline'].'</p>'
		.         '<ul>'
		.         '<li><a href="'.$this->set_url($this->config->dir.'help').'">Study Rooms Help</a> &#8250;</li>'
		.         '<li><a href="'.$this->set_url($this->config->dir.'about').'">Guidelines &amp; Policy</a> &#8250;</li>'
		.         '<li><a href="'.$this->config->template['more_link_url'].'">'.$this->config->template['more_link_text'].'</a> &#8250;</li>'
		.         '</ul>'
		.         '<p class="srs-options">'.$this->config->template['studyrooms_options'].'</p>'
		.         '</footer>';

		return $footer;
	}

	private function send_confirmation($alert,$room_id,$username,$start_datetime,$end_datetime,$session_subject,$session_notes)
	{
		$start_timestamp = strtotime($start_datetime);
		$end_timestamp = strtotime($end_datetime);
		$room = $this->db->get_room($room_id);
		$room_map_link = '';
		if(!empty($room['info_url']))
		{
			$room_map_link = '&#8249;<a href="'.$this->config->full_path.$room['info_url'].'">map</a>&#8250;';
		}
		
		switch($alert)
		{
			case 'cancel':
				$greeting = 'The following reservation has been <b>canceled</b>:';
				break;
			case 'save':
				$greeting = 'Your reservation has been saved:';
				break;
			case 'update':
				$greeting = 'The following reservation has been updated:';
				break;
		}

		$message = '';
		$headers = '';
		$to = $username.$this->config->email_domain;
		
		$headers .= "MIME-Version: 1.0\r\n"
		.           "Content-type: text/html; charset=UTF-8\r\n"
		.           "From:".$this->config->admin_email."\r\n"
		.           "Reply-To:".$to."\r\n";
		
		$subject = $this->config->template['email_subject'];

		$message .= $greeting.'<br><br>'
		.           ((!empty($session_subject)) ? '<strong>Subject</strong>: '.$session_subject.'<br>' : '')
		.           ((!empty($session_notes)) ? '<strong>Notes</strong>: '.$session_notes.'<br>' : '')
		.           '<strong>Date</strong>: '.date('l, F j, Y',$start_timestamp).'<br>'
		.           '<strong>Time</strong>: '.date('g:i A',$start_timestamp).' - '.date('g:i A',$end_timestamp).'<br>'
		.           '<strong>Location</strong>: '.$room['room_name'].' ('.$room['location'].') '.$room_map_link.'<br><br>'
		.           $this->config->template['tagline'].' '.$this->config->domain.$this->config->dir.'<br><br>';

		if(!empty($to))
		{
			$this->mailer->dispatch($to,$subject,$message,$headers);
		}
	}

	/* --------------------------------------------------------------------- */

	private function key($key)
	{
		return !empty($_GET[$key]) ? $_GET[$key] : (!empty($_POST[$key]) ? $_POST[$key] : false);
	}

	private function get_time_diff($from,$to='')
	{
		if(empty($to))
		{
        	$to = time();
    	}
		$diff = (int)abs($to-$from);
		return round($diff/3600); // seconds in 1 hour
	}

	private function post_login()
	{
		$this->user->clear_login();
		
		$_post_username = '';
		$_post_password = '';
		extract($_POST,EXTR_PREFIX_ALL,'_post');
		
		$_post_username = strtolower($_post_username);
		
		if($this->user->bind_login($_post_username,$_post_password))
		{
			$this->user->save_login($_post_username);
			
			$this->check_time_limits();

			$this->display_success_message($this->config->messages['success']['post_login']);
		}
		else
		{
			$this->display_error_message($this->config->messages['error']['login_fail']);
		}
	}

	private function post_cancel()
	{
		$this->post_process_reservation();
	}

	private function post_save()
	{
		$this->post_process_reservation();
	}

	private function post_process_reservation()
	{
		$reservations = $this->parse_reservations($this->get_selected_reservations());
		
		$i = 1;
		
		$_post_username = '';
		$_post_password = '';
		$_post_reminder = '';
		$_post_cell_number = '';
		$_post_cell_provider = '';
		$_post_subject = '';
		$_post_notes = '';
		$_post_open = '';
		$_post_close = '';
		extract($_POST,EXTR_PREFIX_ALL,'_post');
		
		$_post_username = strtolower($_post_username);
		
		// @todo check if room is active during requested reservation
		
		$user_login = $this->user->is_login($_post_username,$_post_password);
		$user_blocked = false;
		if($this->user->is_blocked($_post_username))
		{
			$user_blocked = true;
			$this->error[] = $this->config->messages['error']['check_user_blocked'];
		}
		$less_than_time_limit = $this->check_time_limit(($this->half_hours_selected*30*60)/(60*60));
		$lacking_time_between = $this->lacking_time_between($reservations);
		
		if($user_login && !$user_blocked && $less_than_time_limit && !$lacking_time_between)
		{
			$this->diff_delete_reservations();
			foreach($reservations as $reservation)
			{
				// reservations
				$reservation_id = !empty($reservation['reservation_id']) ? $reservation['reservation_id'] : false;
				$room_id = $reservation['room_id'];
				$start_ymd = date('Y-m-d',$reservation['start_timestamp']);
				$start_datetime = date('Y-m-d G:i:s',$reservation['start_timestamp']);
				$end_datetime = date('Y-m-d G:i:s',$reservation['end_timestamp']);
				
				// session
				$session_id = '';
				$_post_subject = $this->key("subject{$i}");
				$_post_notes = $this->key("notes{$i}");
				$_post_reminder = $this->key("reminder{$i}");
				$_post_cell_number = $this->key("cell_number{$i}");
				$_post_cell_provider = $this->key("cell_provider{$i}");
				
				// check
				$is_reservation = $this->check_this_reservation($room_id,$start_datetime,$end_datetime);
				$time_available = $this->check_time_available($start_datetime,$end_datetime);
				$room_available = $this->check_room_available($room_id,$start_datetime,$end_datetime);
				
				if($is_reservation && $time_available && $room_available)
				{
					if(!empty($reservation_id))
					{
						$this->update_reservation($reservation_id,$_post_subject,$_post_notes,$room_id,$_post_username,$start_datetime,$end_datetime,$_post_reminder,$start_ymd);
					}
					else
					{
						$this->add_reservation($_post_subject,$_post_notes,$room_id,$_post_username,$start_datetime,$end_datetime,$_post_reminder,$_post_open,$_post_close);
					}
				}
				else
				{
					$this->error[] = sprintf($this->config->messages['error']['post_process_reservation'],date('l, F j, Y g:i A',strtotime($start_datetime)));
				}
				$i++;
			}

			if($this->check_reminder($_post_username,$_post_reminder,$_post_cell_number,$_post_cell_provider))
			{
				$this->add_update_user_meta($_post_username,$_post_cell_number,$_post_cell_provider);
			}
			// $this->user->save_login($_post_username);
		}
	}

	private function update_reservation($reservation_id,$subject,$notes,$room_id,$username,$start_datetime,$end_datetime,$reminder,$start_ymd)
	{
		if(!$this->db->get_existing_reservation($room_id,$username,$start_datetime,$end_datetime,$start_ymd))
		{
			$session_id = '';
			$reservation_session = $this->db->get_reservation_session($reservation_id);
			if(!empty($reservation_session['session_id']))
			{
				$session_id = $reservation_session['session_id'];
			}
			// session notes
			if(!empty($subject) || !empty($notes))
			{
				if(!empty($session_id))
				{
					$update_session = $this->db->update_session($session_id,$subject,$notes);
					if(!empty($update_session['error']))
					{
						$this->error[] = $update_session['error'];
					}
				}
				else
				{
					$add_session = $this->db->add_session($subject,$notes);
					if(!empty($add_session['error']))
					{
						$this->error[] = $add_session['error'];
					}
				}
			}
			if(empty($subject) && empty($notes))
			{
				$delete_session = $this->db->delete_session($session_id);
				if(empty($delete_session['error']))
				{
					$session_id = '';
				}
			}
			// reservation
			$update_reservation = $this->db->update_reservation($reservation_id,$room_id,$username,$start_datetime,$end_datetime,$session_id,$reminder);
			if(empty($update_reservation['error']))
			{
				$this->send_confirmation('update',$room_id,$username,$start_datetime,$end_datetime,$subject,$notes);
				$this->success[] = $this->config->messages['success']['update_reservation'];
			}
			else
			{
				$this->error[] = $update_reservation['error'];
			}
		}
		else
		{
			$this->error[] = sprintf($this->config->messages['error']['update_reservation'],date('l, F j, Y g:i A',strtotime($start_datetime)));
		}
	}

	private function add_reservation($subject,$notes,$room_id,$username,$start_datetime,$end_datetime,$reminder,$open,$close)
	{
		if(!$this->overbooked_reservation($username,$room_id,$start_datetime,$end_datetime) && !$this->over_day_hour_limit($open,$close,$start_datetime,$end_datetime,$username))
		{
			// session notes
			$session_id = '';
			if(!empty($subject) || !empty($notes))
			{
				$add_session = $this->db->add_session($subject,$notes);
				if(!empty($add_session['error']))
				{
					$this->error[] = $add_session['error'];
				}
			}
			// reservation
			$add_reservation = $this->db->add_reservation($room_id,$username,$start_datetime,$end_datetime,$session_id,$reminder);
			if(empty($add_reservation['error']))
			{
				$this->send_confirmation('save',$room_id,$username,$start_datetime,$end_datetime,$subject,$notes);
				$this->success[] = $this->config->messages['success']['add_reservation'];
			}
			else
			{
				$this->error[] = $add_reservation['error'];
			}
		}
	}

	private function add_update_user_meta($username,$cell_number,$cell_provider)
	{
		$meta_key = array();
		
		if(!empty($cell_number) && !empty($cell_provider))
		{
			if($cell_number)
			{
				$cell_number = preg_replace('/[^0-9]/','',$cell_number);
				$meta_key['cell_number'] = $cell_number;
			}
			if($cell_provider)
			{
				$meta_key['provider'] = $cell_provider; 
			}
		}
		
		foreach($meta_key as $this_meta_key => $this_meta_value)
		{
			if($this->db->get_user_meta_value($username,$this_meta_key))
			{
				$update_user_meta = $this->db->update_user_meta($username,$this_meta_key,$this_meta_value);
				if(!empty($update_user_meta['error']))
				{
					$this->error[] = $update_user_meta['error'];
				}
			}
			else
			{
				$add_user_meta = $this->db->add_user_meta($username,$this_meta_key,$this_meta_value);
				if(!empty($add_user_meta['error']))
				{
					$this->error[] = $add_user_meta['error'];
				}
			}
		}
	}

	private function diff_delete_reservations()
	{
		$active_id = $this->key('active_reservation_id') ? explode(':',$this->key('active_reservation_id')) : array();
		
		$this_reservation = array();
		$this_session['subject'] = '';
		$this_session['notes'] = '';
		
		foreach($active_id as $reservation_id)
		{
			$this_reservation = $this->db->get_reservation($reservation_id);

			if(!in_array($reservation_id,$this->updated_reservations) && strtotime($this_reservation['start_datetime']) >= $this->this_half_hour())
			{
				if(!empty($this_reservation['session_id']))
				{
					$this_session = $this->db->get_session($this_reservation['session_id']);
					$delete_session = $this->db->delete_session($this_reservation['session_id']);
				}

				$delete_reservation = $this->db->delete_reservation($reservation_id);
				if(empty($delete_reservation['error']))
				{
					$this->send_confirmation('cancel',$this_reservation['room_id'],$this_reservation['username'],$this_reservation['start_datetime'],$this_reservation['end_datetime'],$this_session['subject'],$this_session['notes']);
					
					// because delete happens as part of _post processing
					// we only display success message 
					// if there are no other reservations being updated
					if(empty($this->updated_reservations))
					{
						$this->success[] = $this->config->messages['success']['delete_reservation'];
					}
				}
				else
				{
					$this->error[] = $delete_reservation['error'];
				}
			}
			else
			{
				// because delete happens as part of _post processing
				// we only display error message
				// if there are no other reservations being updated
				if(empty($this->updated_reservations))
				{
					$this->error[] = $this->config->messages['error']['delete_reservation'];
				}
			}
		}
	}

	private function build_reservation_link($reservation)
	{
		$room = $this->db->get_room($reservation['room_id']);
		$session = $this->db->get_session($reservation['session_id']);

		$start_timestamp = strtotime($reservation['start_datetime']);
		$end_timestamp = strtotime($reservation['end_datetime']);

		$date = date('Y-m-d',$start_timestamp);
		$d = explode('-',$date);
		$day_of_week = date('D ',$start_timestamp);
		
		$today_alert = '';
		if($this->is_today($date))
		{
			$today_alert = '&#8249;<strong>Today</strong>&#8250; ';
		}

		$text = array();
		if(!empty($session['subject']))
		{
			$text[] = $session['subject'];
		}
		if(!empty($session['note']))
		{
			$text[] = $session['note'];
		}

		$url = $this->config->dir.$d[0].'/'.$d[1].'/'.$d[2];
		$date = date('n/j/Y',$start_timestamp);
		$starts = date('g:iA',$start_timestamp);
		$ends = date('g:iA',$end_timestamp);
		$this_room = $room['room_name'];
		$location = $room['location'];

		$this_reservation = '<a href="'.$this->set_url($url).'">'.$today_alert.$day_of_week.$date.'</a>:';
		if(!empty($text))
		{
			$this_reservation = '<a href="'.$this->set_url($url).'">'.implode(' ',$text).'</a>: '.$today_alert.$day_of_week.$date;
		}

		return $this_reservation.' '.$starts.' - '.$ends.' in <em>'.$this_room.' ('.$location.')</em>';
	}

	private function lacking_time_between($reservations)
	{
		$interval = 30*60; // half hour
		
		for($i = 0; $i < count($reservations); $i++)
		{
			for($j = 0; $j < count($reservations); $j++)
			{
				if($j != $i)
				{
					if($reservations[$i]['room_id'] == $reservations[$j]['room_id'])
					{
						if($reservations[$i]['end_timestamp'] + $interval == $reservations[$j]['start_timestamp'])
						{
							$this->error[] = $this->config->messages['error']['lacking_time_between'];
							return true;
						}
					}
				}
			}
		}
		return false;
	}

	private function this_half_hour()
	{
		$half_hour = 30*60;
		$this_hour = strtotime(date('Y-m-d ga'));
		return ((time() - $this_hour) >= $half_hour) ? $this_hour + $half_hour : $this_hour;	
	}

	private function get_hour_difference($start,$end)
	{
		$start = strtotime($start);
		$end = strtotime($end);
		return ($end - $start)/(60*60);
	}

	private function overbooked_reservation($username,$room_id,$start_datetime,$end_datetime)
	{
		if($this->db->get_double_booked_reservation($username,$room_id,$start_datetime,$end_datetime))
		{
			if($this->user->is_admin())
			{
				// @todo add admin confirmation?
				// <p class="admin-double-booked">You have another reservation at the same time. <label>Continue? <input type="checkbox" name="admin-double-booked"></label></p>
				return false;
			}

			$this->error[] = $this->config->messages['error']['user_double_booked'];

			return true;
		}

		return false;
	}

	private function over_day_hour_limit($open,$close,$this_start_datetime,$this_end_datetime,$username)
	{
		$total = 0;
		$bookings = $this->db->get_user_reservations_by_day($username,$open,$close);
		
		if(!empty($bookings))
		{
			foreach($bookings as $booking)
			{
				$total += $this->get_hour_difference($booking['start_datetime'],$booking['end_datetime']);
			}
		}
		$total += $this->get_hour_difference($this_start_datetime,$this_end_datetime);

		if($total > $this->day_hour_limit)
		{
			$this->error[] = $this->config->messages['error']['over_day_hour_limit'];

			return true;
		}

		return false;
	}

	private function get_selected_reservations()
	{
		$selected_reservations = array();
		
		foreach($_POST as $k => $v)
		{
			if(strpos($k,'srr-') !== false)
			{
				$k = explode('-',$k);
				$room_id = $k[1];
				$timestamp = $k[2];
				$selected_reservations[$room_id][] = $timestamp;
				$this->half_hours_selected++;
			}	
		}

		return $selected_reservations;
	}

	private function parse_reservations($reservations)
	{
		$id = explode(':',$this->key('active_reservation_id'));
		$updated = array();
		$j = 0;
		$half_hour = 30*60;
		
		if(!empty($reservations))
		{
			foreach($reservations as $room_id => $timestamp)
			{
				sort($timestamp);
				for($i = 0; $i < count($timestamp); $i++)
				{
					$v = $_POST['srr-'.$room_id.'-'.$timestamp[$i]];
					if($i == 0)
					{
						$reservation[$j]['room_id'] = $room_id;
						$reservation[$j]['start_timestamp'] = $timestamp[$i];
						$reservation[$j]['end_timestamp'] = ($timestamp[$i] + $half_hour);
						if(!empty($id) && in_array($v,$id) && !in_array($v,$updated))
						{
							$reservation[$j]['reservation_id'] = $v;
							$updated[] = $v;
						}
					}
					else
					{
						if($timestamp[$i] == ($timestamp[$i - 1] + $half_hour))
						{
							$reservation[$j]['end_timestamp'] = ($timestamp[$i] + $half_hour);
							if(!empty($id) && in_array($v,$id) && !in_array($v,$updated))
							{
								$reservation[$j]['reservation_id'] = $v;
								$updated[] = $v;
							}
						}
						else
						{
							$j++;
							$reservation[$j]['room_id'] = $room_id;
							$reservation[$j]['start_timestamp'] = $timestamp[$i];
							$reservation[$j]['end_timestamp'] = ($timestamp[$i] + $half_hour);
							if(!empty($id) && in_array($v,$id) && !in_array($v,$updated))
							{
								$reservation[$j]['reservation_id'] = $v;
								$updated[] = $v;
							}
						}
					}
				}
				$j++;
			}
			foreach($reservation as $r)
			{
				if(!empty($r['reservation_id']))
				{
					$this->updated_reservations[] = $r['reservation_id'];
				}
			}
			return $reservation;
		}
		return array();
	}

	private function rerun_parsed_reservations($reserves)
	{
		$j = 0;
		$half_hour = 30*60;
		$reservation = '';
		
		if(!empty($reserves))
		{
			foreach($reserves as $reserved)
			{
				$start_timestamp = strtotime($reserved['start_datetime']);
				$end_timestamp = strtotime($reserved['end_datetime']);
				
				$half_hours = ($end_timestamp - $start_timestamp) / $half_hour;
				$timestamp = $start_timestamp;
				
				for($i = 0; $i < $half_hours; $i++)
				{
					$reservation[$reserved['room_id']][] = $timestamp;
					$this->rdiff[$reserved['room_id'].'-'.$timestamp]['username'] = $reserved['username'];
					$this->rdiff[$reserved['room_id'].'-'.$timestamp]['reservation_id'] = $reserved['reservation_id'];
					$this->rdiff[$reserved['room_id'].'-'.$timestamp]['session_id'] = $reserved['session_id'];
					$timestamp += $half_hour;
				}
			}
			ksort($reservation);
		}
		return $reservation;
	}

	private function check_time_limits()
	{
		if($this->user->is_admin())
		{
			// no admin-specific settings at this time
			// extended settings apply to admin
		}
		if($this->user->is_extended())
		{
			$this->days_allowed_past = $this->config->days_allowed['past']['extended'];
			$this->days_allowed_future = $this->config->days_allowed['future']['extended'];
			$this->day_hour_limit = $this->config->day_hour_limit['extended'];

			$this->set_extended_js();
		}
	}

	private function check_room_available($room_id,$start_datetime,$end_datetime)
	{
		$available = array();
		$rooms = $this->db->get_rooms_by_date($start_datetime);
		foreach($rooms as $room)
		{
			$available[] = $room['room_id'];
		}
		return in_array($room_id,$available);
	}

	private function set_url($href)
	{
		preg_match('/('.$this->config->force_ssl.')/',$href,$match);
		if(!empty($match[1]) && empty($_SERVER['HTTPS']) && strpos($href,'https://'.$this->config->domain) === false)
		{
			$href = 'https://'.$this->config->domain.$href;
		}
		// if(empty($match[1]) && !empty($_SERVER['HTTPS']) && strpos($href,'http://'.$this->config->domain) === false)
		// {
		// 	$href = 'http://'.$this->config->domain.$href;
		// }

		return $href;
	}

	private function set_extended_js()
	{
		$this->page_assets['js'][] = '<script>addLoadListener(studyrooms.grid.set_extended_limit);</script>';
	}

	private function set_page_title($title)
	{
		$this->page_title = $title;
		$this->page_assets['title'] .= ' - '.$title;

		// @todo fix this
		if(!empty($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] == $this->config->dir)
		{
			$this->page_assets['title'] = 'Study Rooms';
		}
	}

	private function get_reservation_date($date)
	{
		$reservation_date = date('l n/j',strtotime($date));
		if($this->is_today($date))
		{
			$reservation_date = 'Today '.date('n/j',strtotime($date));
		}

		return $reservation_date;
	}

	private function is_today($date)
	{
		return ($date == date('Y-m-d'));
	}

	private function check_this_reservation($room_id,$start_datetime,$end_datetime)
	{
		if(!empty($room_id) && !empty($start_datetime) && !empty($end_datetime))
		{
			return true;
		}
		else
		{
			$this->error[] = $this->config->messages['error']['check_this_reservation'];

			return false;
		}
	}

	private function check_time_limit($hours)
	{
		if($hours <= $this->day_hour_limit)
		{
			return true;
		}
		else
		{
			$this->error[] = $this->config->messages['error']['check_time_limit'];

			return false;
		}
	}

	private function check_time_available($start_datetime,$end_datetime)
	{
		$start_timestamp = strtotime($start_datetime);
		$end_timestamp = strtotime($end_datetime);
		$this_half_hour_timestamp = $this->this_half_hour();
		$days_allowed_future_timestamp = mktime(0,0,0,date('m'),date('d')+$this->days_allowed_future,date('Y'));

		if($start_timestamp >= $this_half_hour_timestamp && $end_timestamp <= $days_allowed_future_timestamp)
		{
			$library_hours = $this->hours->get_hours($start_datetime,$this->config->hours_of_operation,$this->days_allowed_future);

			if($start_timestamp >= $library_hours['open'] && $end_timestamp <= $library_hours['close'])
			{
				return true;
			}
			else
			{
				$this->error[] = $this->config->messages['error']['check_library_available'];

				return false;
			}
		}
		else
		{
			$this->error[] = $this->config->messages['error']['check_time_available'];

			return false;
		}
	}

	private function check_reminder($username,$reminder,$cell_number,$cell_provider)
	{
		if($reminder == 'txt')
		{
			$args = func_get_args();
			print_r($args);

			$cell_number = !empty($cell_number) ? $cell_number : $this->db->get_user_meta_value($username,'cell_number');
			$cell_provider = !empty($cell_provider) ? $cell_provider : $this->db->get_user_meta_value($username,'provider');
			if(!empty($cell_number) && !empty($cell_provider))
			{
				return true;
			}
			else
			{
				$this->error[] = $this->config->messages['error']['check_reminder'];

				return false;
			}
		}
		
		return true;
	}

	private function redirect_qdate()
	{
		$qdate = $this->key('qdate');
		
		if($this->user->is_extended() && $qdate)
		{
			header('Location: '.$this->config->dir.date('Y/m/d',strtotime($qdate)));
			exit;
		}
	}

	private function check_login_redirect()
	{
		$referer = $this->key('referer');

		// @todo fix redirects for locations requiring login
		if(empty($referer) || strpos($referer,'log'))
		{
			$referer = 'http://'.$this->config->domain.$this->config->dir;
		}

		if($this->user->is_logged_in() && strpos($referer,$this->config->domain.$this->config->dir))
		{
			header('Location: '.$referer);
			exit;
		}
	}
}
