/**
 * effects
 */
function setBackground(element,rgb)
{
	element.style.backgroundColor = 'rgb('+rgb[0]+','+rgb[1]+','+rgb[2]+')';
}

function fadeCycle(element,rgb,ergb,x)
{
	setTimeout(function()
	{
		rgb[0] = (rgb[0] >= ergb[0]) ? ergb[0] : (rgb[0] + 5 >= ergb[0]) ? ergb[0] : rgb[0] + 5;
		rgb[1] = (rgb[1] >= ergb[1]) ? ergb[1] : (rgb[1] + 5 >= ergb[1]) ? ergb[1] : rgb[1] + 5;
		rgb[2] = (rgb[2] >= ergb[2]) ? ergb[2] : (rgb[2] + 5 >= ergb[2]) ? ergb[2] : rgb[2] + 5;
		setBackground(element,rgb);
	},
	(x+1)*10);
}

function fadeOut(element,rgb,ergb,cb)
{
	setBackground(element,rgb[0],rgb[1],rgb[2]);
	for(var i = 0; i <= 255; i += 5)
	{
		fadeCycle(element,rgb,ergb,i);
	}
	if(typeof cb === 'function')
	{
		setTimeout(function(){cb();},3000);
	}
}

/**
 * extensions
 */
function addLoadListener(fn)
{
	if(typeof window.addEventListener !== 'undefined')
	{
		window.addEventListener('load',fn,false);
	}
	else if(typeof document.addEventListener !== 'undefined')
	{
		document.addEventListener('load',fn,false);
	}
	else if(typeof window.attachEvent !== 'undefined')
	{
		window.attachEvent('onload',fn);
	}
	else
	{
		return false;
	}
	
	return true;
}

function attachEventListener(target,eventType,functionRef,capture)
{
	if(typeof target.addEventListener !== 'undefined')
	{
		target.addEventListener(eventType,functionRef,capture);
	}
	else if(typeof target.attachEvent !== 'undefined')
	{
		target.attachEvent('on'+eventType,functionRef);
	}
	else
	{
		return false;
	}

	return true;
}

function detachEventListener(target,eventType,functionRef,capture)
{
	if(typeof target.removeEventListener !== 'undefined')
	{
		target.removeEventListener(eventType,functionRef,capture);
	}
	else if(typeof target.detachEvent !== 'undefined')
	{
		target.detachEvent('on'+eventType,functionRef);
	}
	else
	{
		return false;
	}

	return true;
}

function hasClass(target,classValue)
{
	var pattern = new RegExp("(^| )" + classValue + "( |$)");

	if(target.className.match(pattern))
	{
		return true;
	}

	return false;
}

function addClass(target,classValue)
{
	if(!hasClass(target,classValue))
	{
		if(target.className === '')
		{
			target.className = classValue;
		}
		else
		{
			target.className += ' ' + classValue;
		}
	}

	return true;
}

function removeClass(target,classValue)
{
	var removedClass = target.className;
	var pattern = new RegExp("(^| )" + classValue + "( |$)");

	removedClass = removedClass.replace(pattern,"$1");
	removedClass = removedClass.replace(/ $/,"");

	target.className = removedClass;

	return true;
}

function getPosition(element)
{
	var positionX = 0;
	var positionY = 0;

	while(element !== null)
	{
		positionX += element.offsetLeft;
		positionY += element.offsetTop;
		element = element.offsetParent;
	}

	return [positionX,positionY];
}

document.getElementsByClassName = function(name)
{
	var results = [];
	var a = document.getElementsByTagName('*');
	for(var i=0; i<a.length; i++)
	{
		if(a[i].className.indexOf(name) !== -1)
		{
			results[results.length] = a[i];
		}
	}
	return results;
};

/**
 * studyrooms
 */
var studyrooms = 
{
	dir: '/studyrooms',

	changes:
	{
		remove_nodes: function()
		{
			var nodes = document.getElementsByClassName('status-alert');
			if(nodes)
			{
				for(var i = 0; i < nodes.length; i++)
				{
					nodes[i].parentNode.removeChild(nodes[i]);
				}
			}
		},

		mark_updates: function()
		{
			var updates = document.getElementsByClassName('status-alert');
			if(updates)
			{
				for(var i = 0; i < updates.length; i++)
				{
					fadeOut(updates[i],[255,187,102],[255,255,255],studyrooms.changes.remove_nodes);
				}
			}
		}
	},

	forms: 
	{
		focus_login_fields: function()
		{
			var u = document.getElementById('username'),
			    p = document.getElementById('password');
			
			if(u && p)
			{
				u.focus();
				if(u.value !== '')
				{
					p.focus();
				}
			}
		},

		set_reminder_containers: function()
		{
			if(!document.getElementById('js-rcount')) { return false; }

			var c = document.getElementById('js-rcount').value;
			for(var i = 1; i <= c; i++)
			{
				var container = document.getElementById('txt'+i);
				var txt = document.getElementById('reminder-txt'+i);
				txt.container = container;
				var eml = document.getElementById('reminder-eml'+i);
				eml.container = container;
				var none = document.getElementById('reminder-none'+i);
				none.container = container;

				txt.onclick = function()
				{
					this.container.className = this.checked ? 'txt show' : 'txt hide';
				};
				eml.onclick = function()
				{
					this.container.className = this.checked ? 'hide' : 'show';
				};
				none.onclick = function()
				{
					this.container.className = this.checked ? 'hide' : 'show';
				};
			}
		},

		run_checks: function()
		{
			studyrooms.forms.focus_login_fields();
			studyrooms.forms.set_reminder_containers();
		}
	},

	grid: 
	{
		form: false,
		is_active: false,
		cells: [],
		selected_cells: [],
		checked_inputs: [],
		submit_button: false,
		reservation_limit: 4, // number of half-hour blocks

		// see ZSR_Study_Rooms::set_extended_js()
		set_extended_limit: function()
		{
			studyrooms.grid.reservation_limit = 1152;
		},

		// January is month 0 in JavaScript
		// 24 * 60 * 60 * 1000 = 86400000
		match_date: function(str)
		{
			var match = false;
			if(str)
			{
				match = str.match(/(\d{4})\/(\d{2})\/(\d{2})/);
			}
			return match;
		},

		parse_date: function(str)
		{
			var match = studyrooms.grid.match_date(str);
			return (match) ? new Date(match[1],match[2]-1,match[3]) : new Date();
		},

		get_previous_date_url: function(str)
		{
			var this_date = studyrooms.grid.parse_date(str);
			var prev_date = new Date(this_date.getTime() - 86400000),
				y = prev_date.getFullYear(),
				m = ('0'+(prev_date.getMonth()+1)).slice(-2),
				d = ('0'+prev_date.getDate()).slice(-2);

			return studyrooms.dir+'/'+y+'/'+m+'/'+d;
		},

		get_next_date_url: function(str)
		{
			var this_date = studyrooms.grid.parse_date(str);
			var next_date = new Date(this_date.getTime() + 86400000), 
				y = next_date.getFullYear(),
				m = ('0'+(next_date.getMonth()+1)).slice(-2), 
				d = ('0'+next_date.getDate()).slice(-2);

			return studyrooms.dir+'/'+y+'/'+m+'/'+d;
		},

		deactivate_grid: function()
		{
			studyrooms.grid.is_active = false;
		},
		
		disable_default_behaviors: function(c)
		{
			c.input.onclick = function(){ return false; };
			c.label.onclick = function(){ return false; };
			c.onselectstart = function(){ return false; };
		},

		activate_cell: function(this_cell)
		{
			this_cell.input = this_cell.getElementsByTagName('input')[0];
			this_cell.label = this_cell.getElementsByTagName('label')[0];
			this_cell.is_selected = false;
			if(this_cell.input && this_cell.label)
			{
				studyrooms.grid.disable_default_behaviors(this_cell);
				this_cell.onmousedown = function()
				{
					studyrooms.grid.is_active = true;
					studyrooms.grid.check_reservation(this);
					// prevent text selection
					return false;
				};
				this_cell.onmousemove = function()
				{
					if(!this.is_selected && (typeof studyrooms.grid !== 'undefined') && !document.getElementById('ghost'))
					{
						studyrooms.grid.check_reservation(this);
					}
				};
				this_cell.onmouseup = function()
				{
					studyrooms.grid.deactivate_grid();
				};
			}
		},
		
		activate_cells: function()
		{
			for(var i = 0; i < studyrooms.grid.cells.length; i++)
			{
				studyrooms.grid.activate_cell(studyrooms.grid.cells[i]);
			}
		},
		
		check_reservation: function(c)
		{
			if(studyrooms.grid.is_active)
			{
				// if full, cancel
				if(studyrooms.grid.selected_cells.length >= studyrooms.grid.reservation_limit && c.className.indexOf('open') !== -1)
				{
					studyrooms.grid.reset_checked_inputs();
					studyrooms.grid.reset_selected_cells();
				}
				// if reserved, undo
				if(c.className.indexOf('reserved') !== -1)
				{
					c.className = c.className.replace('reserved','open');
					c.className = c.className.replace('user','');
					c.input.checked = false;
					c.is_selected = false;
				}
				// if reserving, undo
				else if(c.className.indexOf('reserving') !== -1)
				{
					c.className = c.className.replace('reserving','open');
					studyrooms.grid.selected_cells.splice(0,1);
					c.input.checked = false;
					c.is_selected = false;
					studyrooms.grid.activate_submit_button();
				}
				// reserving
				else
				{
					c.className = c.className.replace('open','reserving');
					studyrooms.grid.selected_cells.push(c);
					c.input.checked = true;
					studyrooms.grid.checked_inputs.push(c.input);
					c.is_selected = true;
					studyrooms.grid.activate_submit_button();
				}
			}
		},

		activate_submit_button: function()
		{
			if(studyrooms.grid.selected_cells.length > 0)
			{
				studyrooms.grid.submit_button.parentNode.className = 'submit active';
			}
			else
			{
				studyrooms.grid.submit_button.parentNode.className = 'submit';
			}
		},
		
		reset_checked_inputs: function()
		{
			if(studyrooms.grid.checked_inputs.length > 0)
			{
				for(var i = 0; i < studyrooms.grid.checked_inputs.length; i++)
				{
					studyrooms.grid.checked_inputs[i].checked = false;
					studyrooms.grid.checked_inputs[i].className = 'interactive';
				}
			}
		},
		
		reset_selected_cells: function()
		{
			if(studyrooms.grid.selected_cells)
			{
				for(var j = 0; j < studyrooms.grid.selected_cells.length; j++)
				{
					studyrooms.grid.selected_cells[j].className = studyrooms.grid.selected_cells[j].className.replace('reserving','open');
					studyrooms.grid.selected_cells[j].is_selected = false;
				}
				studyrooms.grid.selected_cells = [];
				studyrooms.grid.activate_submit_button();
			}
		},

		activate_inputs: function()
		{
			var inputs = studyrooms.grid.form.getElementsByTagName('input');
			for(var i = 0; i < inputs.length; i++)
			{
				if(inputs[i].getAttribute('type') === 'checkbox')
				{
					inputs[i].className = 'interactive';
				}
			}
			studyrooms.grid.reset_selected_cells();
		},

		ghost: 
		{
			target: null,
			origin: [],
			hotspots: [],

			chase: function(event)
			{
				var this_ghost, ghost_shadow,
				    closest, closestY,
				    ghostX, ghostY,
				    distanceY;

				studyrooms.grid.deactivate_grid();

				if(typeof event === 'undefined')
				{
					event = window.event;
				}
				
				this_ghost = document.getElementById('ghost');
				
				if(this_ghost !== null)
				{
					this_ghost.style.marginLeft = event.clientX - studyrooms.grid.ghost.origin[0] + 'px';
					this_ghost.style.marginTop = event.clientY - studyrooms.grid.ghost.origin[1] + 'px';
				}
				
				closest = null;
				closestY = null;
				
				for(var i in studyrooms.grid.ghost.hotspots)
				{
					ghostX = parseInt(this_ghost.style.left, 10) + parseInt(this_ghost.style.marginLeft, 10);
					ghostY = parseInt(this_ghost.style.top, 10) + parseInt(this_ghost.style.marginTop, 10);
					
					if(ghostX >= studyrooms.grid.ghost.hotspots[i].offsetX - this_ghost.offsetWidth && ghostX <= studyrooms.grid.ghost.hotspots[i].offsetX + studyrooms.grid.ghost.hotspots[i].element.offsetWidth)
					{
						distanceY = Math.abs(ghostY - studyrooms.grid.ghost.hotspots[i].offsetY);
						
						if(closestY === null || closestY > distanceY)
						{
							closest = studyrooms.grid.ghost.hotspots[i];
							closestY = distanceY;
						}
					}
				}
				
				if(closest !== null)
				{
					ghost_shadow = document.getElementById('ghost_shadow');
					
					if(ghost_shadow === null)
					{
						ghost_shadow = document.createElement('div');
						ghost_shadow.id = 'ghost_shadow';
						ghost_shadow.className = studyrooms.grid.ghost.target.className;
						document.getElementsByTagName('body')[0].appendChild(ghost_shadow);
					}
					
					ghost_shadow.marked = closest.element;
					
					ghost_shadow.style.left = (1 + closest.offsetX) + 'px';
					ghost_shadow.style.top = (1 + closest.offsetY) + 'px';
					ghost_shadow.style.width = (this_ghost.offsetWidth - 1) + 'px';
				}
				else
				{
					ghost_shadow = document.getElementById('ghost_shadow');
					
					if(ghost_shadow !== null)
					{
						ghost_shadow.parentNode.removeChild(ghost_shadow);
					}
					
				}
				
				event.returnValue = false;

				if(typeof event.preventDefault !== 'undefined')
				{
					event.preventDefault();
				}

				return true;
			},

			release: function()
			{
				var this_ghost, ghost_shadow;

				studyrooms.grid.deactivate_grid();

				detachEventListener(document,'mousemove',studyrooms.grid.ghost.chase,false);
				detachEventListener(studyrooms.grid.ghost.target,'mousedown',studyrooms.grid.ghost.trap,false);
				
				this_ghost = document.getElementById('ghost');
				
				if(this_ghost !== null)
				{
					this_ghost.parentNode.removeChild(this_ghost);
				}
				
				ghost_shadow = document.getElementById('ghost_shadow');
				
				if(ghost_shadow !== null)
				{
					ghost_shadow.marked.className = ghost_shadow.marked.className.replace('open','reserving');
					ghost_shadow.marked.input.checked = true;
					studyrooms.grid.ghost.target.className = studyrooms.grid.ghost.target.className.replace('reserved','open');
					addClass(studyrooms.grid.ghost.target,'open');
					removeClass(studyrooms.grid.ghost.target,'reserving');
					removeClass(studyrooms.grid.ghost.target,'current_user_reservations');
					attachEventListener(ghost_shadow.marked.getElementsByTagName('i')[0],'mousedown',studyrooms.grid.ghost.trap,false);
					addClass(ghost_shadow.marked.getElementsByTagName('i')[0],'draggable');
					ghost_shadow.marked.input.value = studyrooms.grid.ghost.target.input.value;
					studyrooms.grid.ghost.target.input.value = 'Y';
					removeClass(studyrooms.grid.ghost.target.getElementsByTagName('i')[0],'draggable');
					studyrooms.grid.deactivate_grid();
					ghost_shadow.parentNode.removeChild(ghost_shadow);
				}
				
				return true;
			},

			trap: function(event)
			{
				var this_ghost, position,
				    rooms, room_position, 
				    cells, cell_position, cell_width;

				studyrooms.grid.deactivate_grid();

				if(typeof event === 'undefined')
				{
					event = window.event;
				}

				if(typeof event.target !== 'undefined')
				{
					studyrooms.grid.ghost.target = event.target.parentNode;
				}
				else
				{
					studyrooms.grid.ghost.target = event.srcElement.parentNode;
				}

				studyrooms.grid.ghost.origin = [event.clientX,event.clientY];
				studyrooms.grid.ghost.hotspots = [];

				rooms = studyrooms.grid.form.getElementsByTagName('dl');
				for(var i = 0; i < rooms.length; i++)
				{
					cells = rooms[i].getElementsByTagName('dd');
					
					for(var j = 0; j < cells.length; j++)
					{
						if(cells[j] !== null && hasClass(cells[j],'open'))
						{
							cell_position = getPosition(cells[j]);
							cell_width = cells[j].offsetWidth;
							
							studyrooms.grid.ghost.hotspots[studyrooms.grid.ghost.hotspots.length] =
							{
								element: cells[j],
								offsetX: cell_position[0],
								offsetY: cell_position[1]
							};
						}
					}
					
					room_position = getPosition(rooms[i]);
					
					studyrooms.grid.ghost.hotspots[studyrooms.grid.ghost.hotspots.length] =
					{
						element: rooms[i],
						offsetX: room_position[0],
						offsetY: room_position[1] + rooms[i].offsetHeight
					};
				}
				
				position = getPosition(studyrooms.grid.ghost.target);
				
				this_ghost = document.createElement('div');
				this_ghost.setAttribute('id','ghost');
				document.getElementsByTagName('body')[0].appendChild(this_ghost);

				this_ghost.appendChild(studyrooms.grid.ghost.target.cloneNode(true));
				this_ghost.style.left = (1 + position[0]) + 'px';
				this_ghost.style.top = (1 + position[1]) + 'px';
				this_ghost.style.width = (cell_width - 1) + 'px';
				
				attachEventListener(document,'mousemove',studyrooms.grid.ghost.chase,false);
				attachEventListener(document,'mouseup',studyrooms.grid.ghost.release,false);
				
				event.returnValue = false;

				if(typeof event.preventDefault !== 'undefined')
				{
					event.preventDefault();
				}

				return true;
			},

			summon: function()
			{
				var current_user_reservations = document.getElementsByClassName('current_user_reservations');
				for(var i = 0; i < current_user_reservations.length; i++)
				{
					var drag_handle = current_user_reservations[i].getElementsByTagName('i')[0];
					
					addClass(drag_handle,'draggable');
					attachEventListener(drag_handle,'mousedown',studyrooms.grid.ghost.trap,false);
					attachEventListener(drag_handle,'mouseover',studyrooms.grid.deactivate_grid,false);
				}
				
				return true;
			}
		},

		enable_kbd: function()
		{
			var current_url = location.href;

			if(current_url.indexOf(studyrooms.dir) !== -1 && (studyrooms.grid.match_date(current_url) || location.pathname === studyrooms.dir+'/'))
			{
				document.onkeydown = function(e)
				{
					var keycode = (window.event) ? window.event.keyCode : e.which;

					// left arrow
					if(keycode === 37)
					{
						location.href = studyrooms.grid.get_previous_date_url(current_url);
					}
					// right arrow
					if(keycode === 39)
					{
						location.href = studyrooms.grid.get_next_date_url(current_url);
					}
				};
			}
		},

		get_elements: function()
		{
			if(!document.getElementById('study_room_reservations') || !document.getElementsByClassName('cell')) { return false; }

			studyrooms.grid.form = document.getElementById('study_room_reservations');
			studyrooms.grid.cells = document.getElementsByClassName('cell');
			studyrooms.grid.submit_button = document.getElementById('save') || document.getElementById('update');

			return true;
		},
		
		register_actions: function()
		{
			if(studyrooms.grid.get_elements())
			{
				studyrooms.grid.activate_inputs();
				studyrooms.grid.activate_cells();
				studyrooms.grid.ghost.summon();
			}
			studyrooms.grid.enable_kbd();
		}
	}
};

addLoadListener(studyrooms.grid.register_actions);
addLoadListener(studyrooms.forms.run_checks);
addLoadListener(studyrooms.changes.mark_updates);
