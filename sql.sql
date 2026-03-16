emslss_api_logs
Field	Type	Null	Key	Default	Extra
id	int(11)	NO	PRI	NULL	auto_increment
source	varchar(50)	YES		NULL	
payload	text	YES		NULL	
response	text	YES		NULL	
created_at	datetime	YES		current_timestamp()


emslss_images
id	int(11)	NO	PRI	NULL	auto_increment
order_id	int(11)	YES		NULL	
image_path	varchar(255)	YES		NULL	
uploaded_by	int(11)	YES		NULL	
created_at	datetime	YES		current_timestamp()


emslss_orders
id	int(11)	NO	PRI	NULL	auto_increment
ems_code	varchar(50)	YES	UNI	NULL	
post_office_name	varchar(255)	YES		NULL	
post_office_address	text	YES		NULL	
holder_name	varchar(100)	YES		NULL	
holder_phone	varchar(20)	YES		NULL	
sender_name	varchar(255)	YES		NULL	
sender_phone	varchar(50)	YES		NULL	
sender_address	text	YES		NULL	
receiver_name	varchar(255)	YES		NULL	
receiver_phone	varchar(50)	YES		NULL	
receiver_address	text	YES		NULL	
weight	decimal(10,2)	YES		NULL	
cargo_type	varchar(255)	YES		NULL	
service_type	enum('door_to_door','door_to_hub','hub_to_door')	YES		NULL	
pickup_shipper_id	int(11)	YES		NULL	
delivery_shipper_id	int(11)	YES		NULL	
status	enum('new_order','assigned_pickup','picked_up','in_transit','assigned_delivery','delivered','failed','cancelled')	YES		new_order	
created_at	datetime	YES		current_timestamp()	
updated_at	datetime	YES		current_timestamp()	on update current_timestamp()


emslss_order_meta
id	int(11)	NO	PRI	NULL	auto_increment
order_id	int(11)	YES		NULL	
meta_key	varchar(100)	YES		NULL	
meta_value	text	YES		NULL	
created_at	datetime	YES		current_timestamp()


emslss_roles
id	int(11)	NO	PRI	NULL	auto_increment
role_code	varchar(50)	YES	UNI	NULL	
role_name	varchar(100)	YES		NULL	
description	text	YES		NULL	


emslss_tracking
id	int(11)	NO	PRI	NULL	auto_increment
order_id	int(11)	YES		NULL	
status	varchar(50)	YES		NULL	
note	text	YES		NULL	
created_by	int(11)	YES		NULL	
created_at	datetime	YES		current_timestamp()	


emslss_users
id	int(11)	NO	PRI	NULL	auto_increment
username	varchar(50)	YES		NULL	
password	varchar(255)	YES		NULL	
full_name	varchar(100)	YES		NULL	
role	enum('admin','dispatcher','shipper','operation')	YES		NULL	
phone	varchar(20)	YES		NULL	
created_at	timestamp	YES		current_timestamp()	
role_id	int(11)	YES	MUL	NULL	
is_active	tinyint(4)	YES		1	