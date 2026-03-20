DESCRIBE themes;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20)
NO
PRI
NULL
name
varchar(255)
NO
NULL
slug
varchar(255)
NO
UNI
NULL
description
text
YES
NULL
thumbnail_url
varchar(500)
YES
NULL
preview_url
varchar(500)
YES
NULL
version
varchar(50)
YES
1.0.0
author
varchar(255)
YES
NULL
is_active
tinyint(1)
YES
MUL
0
is_default
tinyint(1)
YES
0
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
tenant_id
int(10) unsigned
NO
MUL
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE color_settings;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
theme_id
bigint(20)
YES
MUL
NULL
setting_key
varchar(100)
NO
NULL
setting_name
varchar(255)
NO
NULL
color_value
varchar(7)
NO
NULL
category
enum('primary','secondary','accent','background','text','border','status','other')
YES
MUL
other
is_active
tinyint(1)
YES
1
sort_order
int(11)
YES
0
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
tenant_id
int(10) unsigned
NO
MUL
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE font_settings;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
theme_id
bigint(20)
YES
MUL
NULL
setting_key
varchar(100)
NO
NULL
setting_name
varchar(255)
NO
NULL
font_family
varchar(255)
NO
NULL
font_size
varchar(50)
YES
NULL
font_weight
varchar(50)
YES
NULL
line_height
varchar(50)
YES
NULL
category
enum('heading','body','button','navigation','other')
YES
other
is_active
tinyint(1)
YES
1
sort_order
int(11)
YES
0
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
tenant_id
int(10) unsigned
NO
MUL
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE design_settings;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
theme_id
bigint(20)
YES
MUL
NULL
setting_key
varchar(100)
NO
NULL
setting_name
varchar(255)
NO
NULL
setting_value
text
YES
NULL
setting_type
enum('text','number','color','image','boolean','select','json')
YES
text
category
enum('layout','header','footer','sidebar','homepage','product','cart','checkout','other')
YES
MUL
other
is_active
tinyint(1)
YES
1
sort_order
int(11)
YES
0
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
tenant_id
int(10) unsigned
NO
MUL
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE button_styles;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
tenant_id
int(10) unsigned
NO
MUL
NULL
theme_id
bigint(20)
YES
MUL
NULL
name
varchar(255)
NO
NULL
slug
varchar(255)
NO
NULL
button_type
enum('primary','secondary','success','danger','warning','info','outline','link')
NO
MUL
NULL
background_color
varchar(7)
NO
NULL
text_color
varchar(7)
NO
NULL
border_color
varchar(7)
YES
NULL
border_width
int(11)
YES
0
border_radius
int(11)
YES
4
padding
varchar(50)
YES
10px 20px
font_size
varchar(50)
YES
14px
font_weight
varchar(50)
YES
normal
hover_background_color
varchar(7)
YES
NULL
hover_text_color
varchar(7)
YES
NULL
hover_border_color
varchar(7)
YES
NULL
is_active
tinyint(1)
YES
1
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE card_styles;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
bigint(20) unsigned
NO
PRI
NULL
auto_increment
tenant_id
int(10) unsigned
NO
MUL
NULL
theme_id
bigint(20)
YES
MUL
NULL
name
varchar(255)
NO
NULL
slug
varchar(255)
NO
NULL
card_type
varchar(50)
NO
MUL
background_color
varchar(7)
YES
#FFFFFF
border_color
varchar(7)
YES
#E0E0E0
border_width
int(11)
YES
1
border_radius
int(11)
YES
8
shadow_style
varchar(100)
YES
none
padding
varchar(50)
YES
16px
hover_effect
enum('none','lift','zoom','shadow','border','brightness')
YES
none
text_align
enum('left','center','right')
YES
left
image_aspect_ratio
varchar(50)
YES
1:1
is_active
tinyint(1)
YES
1
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()

 
