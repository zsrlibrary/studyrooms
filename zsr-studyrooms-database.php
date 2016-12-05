<?php

class ZSR_Study_Rooms_Database
{
	public $connection;
	private $config;

	function __construct()
	{
		require_once 'zsr-studyrooms-config.php';
		$this->config = new ZSR_Study_Rooms_Config();
	}

	public function connect()
	{
		$this->connection = new mysqli($this->config->mysql['host'],$this->config->mysql['username'],$this->config->mysql['password'],$this->config->mysql['dbname']) or die('Cannot open database.');
	}

	public function disconnect()
	{
		if(!empty($this->connection))
		{
			$this->connection->close();
		}
	}

	/* --------------------------------------------------------------------- */

	public function get_reservations_by_date($date)
	{
		$reservations_by_date = array();

		if(!empty($date))
		{
			$like_date = $date.'%';

			$sql = 'SELECT reservation_id, room_id, username, start_datetime, end_datetime, session_id FROM reservation WHERE start_datetime LIKE ?';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('s',$like_date);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($reservation_id,$room_id,$username,$start_datetime,$end_datetime,$session_id);
				while($statement->fetch())
				{
					$reservations_by_date[] = compact('reservation_id','room_id','username','start_datetime','end_datetime','session_id');
				}
			}
			$statement->free_result();
		}

		return $reservations_by_date;
	}

	public function get_existing_reservation($room_id,$username,$start_datetime,$end_datetime,$start_ymd)
	{
		$existing_reservation = false;

		if(!empty($room_id) && !empty($username) && !empty($start_datetime) && !empty($end_datetime) && !empty($start_ymd))
		{
			$like_start_ymd = $start_ymd.'%';

			$sql = 'SELECT reservation_id FROM reservation WHERE (room_id = ? OR username = ?) AND ((start_datetime >= ? AND start_datetime < ?) OR (start_datetime < ? AND end_datetime > ?) OR (end_datetime > ? AND end_datetime <= ?)) AND reservation_id NOT IN (SELECT reservation_id FROM reservation WHERE username = ? AND start_datetime LIKE ?)';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('isssssssss',$room_id,$username,$start_datetime,$end_datetime,$start_datetime,$end_datetime,$start_datetime,$end_datetime,$username,$like_start_ymd);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($reservation_id);
				$statement->fetch();
			}
			$statement->free_result();
		}

		return $existing_reservation;
	}

	public function get_double_booked_reservation($username,$room_id,$start_datetime,$end_datetime)
	{
		$double_booked = false;
		
		if(!empty($room_id) && !empty($username) && !empty($start_datetime) && !empty($end_datetime))
		{
			$sql = 'SELECT reservation_id FROM reservation WHERE (room_id = ? OR username = ?) AND ((start_datetime >= ? AND start_datetime < ?) OR (start_datetime < ? AND end_datetime > ?) OR (end_datetime > ? AND end_datetime <= ?))';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('isssssss',$room_id,$username,$start_datetime,$end_datetime,$start_datetime,$end_datetime,$start_datetime,$end_datetime);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($double_booked);
				$statement->fetch();
			}
			$statement->free_result();
		}

		return $double_booked;
	}

	public function get_user_reservations($username)
	{
		$user_reservations = array();

		if(!empty($username))
		{
			$date = date('Y-m-d G');

			$sql = 'SELECT reservation_id, room_id, username, start_datetime, end_datetime, session_id FROM reservation WHERE username LIKE ? AND start_datetime > ? ORDER BY start_datetime';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('ss',$username,$date);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($reservation_id,$room_id,$username,$start_datetime,$end_datetime,$session_id);
				while($statement->fetch())
				{
					$user_reservations[] = compact('reservation_id','room_id','username','start_datetime','end_datetime','session_id');
				}
			}
			$statement->free_result();
		}

		return $user_reservations;
	}

	public function get_user_reservations_by_day($username,$open,$close)
	{
		$user_reservations_by_day = array();

		if(!empty($username) && !empty($open) && !empty($close))
		{
			$open_date = date('Y-m-d G:i:s',$open);
			$close_date = date('Y-m-d G:i:s',$close);

			$sql = 'SELECT reservation_id, room_id, username, start_datetime, end_datetime, session_id FROM reservation WHERE username LIKE ? AND start_datetime >= ? AND end_datetime <= ?';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('sss',$username,$open_date,$close_date);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($reservation_id,$room_id,$username,$start_datetime,$end_datetime,$session_id);
				while($statement->fetch())
				{
					$user_reservations_by_day[] = compact('reservation_id','room_id','username','start_datetime','end_datetime','session_id');
				}
			}
			$statement->free_result();
		}

		return $user_reservations_by_day;
	}

	public function get_reservation($reservation_id)
	{
		$reservation = array();

		if(!empty($reservation_id))
		{
			$sql = 'SELECT room_id, username, start_datetime, end_datetime, session_id, reminder FROM reservation WHERE reservation_id = ?';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('i',$reservation_id);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($reservation['room_id'],$reservation['username'],$reservation['start_datetime'],$reservation['end_datetime'],$reservation['session_id'],$reservation['reminder']);
				$statement->fetch();
			}
			$statement->free_result();
		}

		return $reservation;
	}

	public function get_reservation_reminder($reservation_id)
	{
		$reminder = '';

		if(!empty($reservation_id))
		{
			$sql = 'SELECT reminder FROM reservation WHERE reservation_id = ?';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('i',$reservation_id);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($reminder);
				$statement->fetch();
			}
			$statement->free_result();
		}

		return $reminder;
	}

	public function get_reservation_session($reservation_id)
	{
		$reservation_session = array();

		if(!empty($reservation_id))
		{
			$sql = 'SELECT session.session_id, subject, notes FROM session, reservation WHERE reservation.reservation_id = ? AND reservation.session_id = session.session_id';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('i',$reservation_id);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($reservation_session['session_id'],$reservation_session['subject'],$reservation_session['notes']);
				$statement->fetch();
			}
			$statement->free_result();
		}

		return $reservation_session;
	}

	public function get_reminder_reservations($datetime)
	{
		$reminder_reservations = array();

		if(!empty($datetime))
		{
			$like_datetime = $datetime.'%';

			$sql = "SELECT username, start_datetime, end_datetime, session_id, room_name, location, info_url FROM reservation, rooms WHERE reservation.start_datetime LIKE ? AND reservation.reminder != '' AND reservation.room_id = rooms.room_id";
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('s',$like_datetime);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($username,$start_datetime,$end_datetime,$session_id,$room_name,$location,$info_url);
				while($statement->fetch())
				{
					$reminder_reservations[] = compact('username','start_datetime','end_datetime','session_id','room_name','location','info_url');
				}
			}
			$statement->free_result();
		}

		return $reminder_reservations;
	}

	public function get_room($room_id)
	{
		$room = array();

		if(!empty($room_id))
		{
			$sql = 'SELECT room_name, equipment, location, capacity, info_url, image_url, offline_startdate, offline_enddate FROM rooms WHERE room_id = ?';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('i',$room_id);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($room['room_name'],$room['equipment'],$room['location'],$room['capacity'],$room['info_url'],$room['image_url'],$room['offline_startdate'],$room['offline_enddate']);
				$statement->fetch();
			}
			$statement->free_result();
		}

		return $room;
	}

	public function get_rooms_by_date($date)
	{
		$rooms_by_date = array();

		if(!empty($date))
		{
			$date = is_int($date) ? $date : strtotime($date);
			$date = date('Y-m-d',$date);

			$sql = 'SELECT room_id, room_name, equipment, location, capacity, info_url, image_url, offline_startdate, offline_enddate FROM rooms WHERE (offline_startdate IS NULL OR UNIX_TIMESTAMP(?) < UNIX_TIMESTAMP(offline_startdate) OR UNIX_TIMESTAMP(?) > UNIX_TIMESTAMP(offline_enddate)) ORDER BY room_name';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('ss',$date,$date);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($room_id,$room_name,$equipment,$location,$capacity,$info_url,$image_url,$offline_startdate,$offline_enddate);
				while($statement->fetch())
				{
					$rooms_by_date[] = compact('room_id','room_name','equipment','location','capacity','info_url','image_url','offline_startdate','offline_enddate');
				}
			}
			$statement->free_result();
		}
		
		return $rooms_by_date;
	}

	public function get_rooms_by_name($name,$date)
	{
		$rooms_by_name = array();

		if(!empty($name) && !empty($date))
		{
			$like_name = '%'.$name.'%';
			$date = is_int($date) ? $date : strtotime($date);
			$date = date('Y-m-d',$date);

			$sql = 'SELECT room_id, room_name, equipment, location, capacity, info_url, image_url, offline_startdate, offline_enddate FROM rooms WHERE room_name LIKE ? AND (offline_startdate IS NULL OR UNIX_TIMESTAMP(?) < UNIX_TIMESTAMP(offline_startdate) OR UNIX_TIMESTAMP(?) > UNIX_TIMESTAMP(offline_enddate)) ORDER BY room_name';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('sss',$like_name,$date,$date);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($room_id,$room_name,$equipment,$location,$capacity,$info_url,$image_url,$offline_startdate,$offline_enddate);
				while($statement->fetch())
				{
					$rooms_by_name[] = compact('room_id','room_name','equipment','location','capacity','info_url','image_url','offline_startdate','offline_enddate');
				}
			}
			$statement->free_result();
		}
		
		return $rooms_by_name;
	}

	public function get_session($session_id)
	{
		$session = array();

		if(!empty($session_id))
		{
			$sql = 'SELECT subject, notes FROM session WHERE session_id = ?';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('i',$session_id);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($session['subject'],$session['notes']);
				$statement->fetch();
			}
			$statement->free_result();
		}

		return $session;
	}

	public function get_user_meta_value($username,$meta_key)
	{
		$user_meta_value = '';

		if(!empty($username) && !empty($meta_key))
		{
			$sql = 'SELECT meta_value FROM user_meta WHERE username = ? AND meta_key = ?';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('ss',$username,$meta_key);
				$exe = $statement->execute();
			}
			if(!empty($exe))
			{
				$statement->bind_result($user_meta_value);
				$statement->fetch();
			}
			$statement->free_result();
		}
		
		return $user_meta_value;
	}

	/* --------------------------------------------------------------------- */

	public function add_reservation($room_id,$username,$start_datetime,$end_datetime,$session_id,$reminder)
	{
		$add_reservation['success'] = false;
		$add_reservation['error'] = false;

		$sql = 'INSERT INTO reservation (room_id,username,start_datetime,end_datetime,session_id,reminder) VALUES (?,?,?,?,?,?)';
		$statement = $this->connection->stmt_init();
		if($statement->prepare($sql))
		{
			$statement->bind_param('isssis',$room_id,$username,$start_datetime,$end_datetime,$session_id,$reminder);
			$exe = $statement->execute();
			$add_reservation['success'] = $this->connection->insert_id;
		}
		if(!$exe)
		{
			$add_reservation['success'] = false;
			$add_reservation['error'] = $statement->error;
		}
		$statement->free_result();

		return $add_reservation;
	}

	public function update_reservation($reservation_id,$room_id,$username,$start_datetime,$end_datetime,$session_id,$reminder)
	{
		$update_reservation['success'] = $reservation_id;
		$update_reservation['error'] = false;

		if(!empty($reservation_id))
		{
			$sql = 'UPDATE reservation SET room_id = ?, username = ?, start_datetime = ?, end_datetime = ?, session_id = ?, reminder = ? WHERE reservation_id = ?';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('isssisi',$room_id,$username,$start_datetime,$end_datetime,$session_id,$reminder,$reservation_id);
				$exe = $statement->execute();
			}
			if(!$exe)
			{
				$update_reservation['success'] = false;
				$update_reservation['error'] = $statement->error;
			}
			$statement->free_result();
		}

		return $update_reservation;
	}

	public function delete_reservation($reservation_id)
	{
		$delete_reservation['success'] = false;
		$delete_reservation['error'] = false;

		if(!empty($reservation_id))
		{
			$sql = 'DELETE FROM reservation WHERE reservation_id = ?';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('i',$reservation_id);
				$exe = $statement->execute();
				$delete_reservation['success'] = true;
			}
			if(!$exe)
			{
				$delete_reservation['success'] = false;
				$delete_reservation['error'] = $statement->error;
			}
			$statement->free_result();
		}

		return $delete_reservation;
	}

	public function add_session($subject,$notes)
	{
		$add_session['success'] = false;
		$add_session['error'] = false;

		$sql = 'INSERT INTO session (subject,notes) VALUES (?,?)';
		$statement = $this->connection->stmt_init();
		if($statement->prepare($sql))
		{
			$statement->bind_param('ss',$subject,$notes);
			$exe = $statement->execute();
			$add_session['success'] = $this->connection->insert_id;
		}
		if(!$exe)
		{
			$add_session['success'] = false;
			$add_session['error'] = $statement->error;
		}
		$statement->free_result();

		return $add_session;
	}

	public function update_session($session_id,$subject,$notes)
	{
		$update_session['success'] = $session_id;
		$update_session['error'] = false;

		if(!empty($session_id))
		{
			$sql = 'UPDATE session SET subject = ?, notes = ? WHERE session_id = ?';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('ssi',$subject,$notes,$session_id);
				$exe = $statement->execute();
			}
			if(!$exe)
			{
				$update_session['success'] = false;
				$update_session['error'] = $statement->error;
			}
			$statement->free_result();
		}

		return $update_session;
	}

	public function delete_session($session_id)
	{
		$delete_session['success'] = false;
		$delete_session['error'] = false;

		if(!empty($session_id))
		{
			$sql = 'DELETE FROM session WHERE session_id = ?';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('i',$session_id);
				$exe = $statement->execute();
				$delete_session['success'] = true;
			}
			if(!$exe)
			{
				$delete_session['success'] = false;
				$delete_session['error'] = $statement->error;
			}
			$statement->free_result();

			return $delete_session;
		}
	}

	public function add_user_meta($username,$meta_key,$meta_value)
	{
		$add_user_meta['success'] = false;
		$add_user_meta['error'] = false;

		if(!empty($username) && !empty($meta_key))
		{
			$sql = 'INSERT INTO user_meta (username,meta_key,meta_value) VALUES (?,?,?)';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('sss',$username,$meta_key,$meta_value);
				$exe = $statement->execute();
				$add_user_meta['success'] = $this->connection->insert_id;
			}
			if(!$exe)
			{
				$add_user_meta['success'] = false;
				$add_user_meta['error'] = $statement->error;
			}
			$statement->free_result();
		}

		return $add_user_meta;
	}

	public function update_user_meta($username,$meta_key,$meta_value)
	{
		$update_user_meta['success'] = $meta_key;
		$update_user_meta['error'] = false;

		if(!empty($username) && !empty($meta_key))
		{
			$sql = 'UPDATE user_meta SET meta_value = ? WHERE username = ? AND meta_key = ?';
			$statement = $this->connection->stmt_init();
			if($statement->prepare($sql))
			{
				$statement->bind_param('sss',$meta_value,$username,$meta_key);
				$exe = $statement->execute();
			}
			if(!$exe)
			{
				$update_user_meta['success'] = false;
				$update_user_meta['error'] = $statement->error;
			}
			$statement->free_result();
		}

		return $update_user_meta;
	}
}
