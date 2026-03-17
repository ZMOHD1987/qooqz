DESCRIBE products;
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
product_type_id
int(10) unsigned
NO
MUL
NULL
tenant_id
int(10) unsigned
NO
MUL
NULL
created_by_user_id
int(11) unsigned
YES
MUL
NULL
sku
varchar(100)
NO
UNI
NULL
slug
varchar(255)
NO
UNI
NULL
barcode
varchar(100)
YES
UNI
NULL
brand_id
bigint(20)
YES
MUL
NULL
is_active
tinyint(1)
YES
MUL
1
is_featured
tinyint(1)
YES
0
is_bestseller
tinyint(1)
YES
0
is_new
tinyint(1)
YES
0
stock_quantity
int(11)
YES
0
low_stock_threshold
int(11)
YES
5
stock_status
enum('in_stock','out_of_stock','on_backorder')
YES
MUL
in_stock
manage_stock
tinyint(1)
YES
1
allow_backorder
tinyint(1)
YES
0
total_sales
int(11)
YES
0
rating_average
decimal(3,2)
YES
MUL
0.00
rating_count
int(11)
YES
0
views_count
int(11)
YES
0
created_at
datetime
YES
MUL
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
published_at
datetime
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_types;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
int(10) unsigned
NO
PRI
NULL
auto_increment
code
varchar(50)
NO
UNI
NULL
name
varchar(100)
NO
NULL
description
varchar(255)
YES
NULL
is_active
tinyint(1)
YES
1
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_translations;
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
auto_increment
product_id
bigint(20) unsigned
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
name
varchar(500)
NO
MUL
NULL
short_description
text
YES
NULL
description
longtext
YES
NULL
specifications
longtext
YES
NULL
meta_title
varchar(255)
YES
NULL
meta_description
text
YES
NULL
meta_keywords
varchar(500)
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_variant_attributes;
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
variant_id
bigint(20)
NO
MUL
NULL
attribute_id
bigint(20)
NO
MUL
NULL
attribute_value_id
bigint(20)
NO
MUL
NULL
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_variants;
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
product_id
bigint(20) unsigned
NO
MUL
NULL
sku
varchar(100)
YES
UNI
NULL
barcode
varchar(100)
YES
UNI
NULL
stock_quantity
int(11)
YES
0
low_stock_threshold
int(11)
YES
5
is_active
tinyint(1)
YES
1
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
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_stock_movements;
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
product_id
bigint(20)
NO
MUL
NULL
variant_id
bigint(20)
YES
MUL
NULL
change_quantity
int(11)
NO
NULL
type
enum('restock','sale','return','adjustment')
NO
NULL
reference_id
bigint(20)
YES
NULL
notes
text
YES
NULL
created_at
datetime
NO
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_stock_alerts;
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
product_id
bigint(20) unsigned
NO
MUL
NULL
variant_id
bigint(20) unsigned
YES
MUL
NULL
user_id
int(11)
NO
MUL
NULL
email
varchar(191)
NO
NULL
is_notified
tinyint(1)
YES
MUL
0
notified_at
datetime
YES
NULL
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_reviews;
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
auto_increment
product_id
bigint(20) unsigned
NO
MUL
NULL
user_id
int(11) unsigned
NO
MUL
NULL
rating
tinyint(4)
NO
MUL
NULL
title
varchar(255)
YES
NULL
comment
text
YES
NULL
is_verified_purchase
tinyint(1)
YES
0
is_approved
tinyint(1)
YES
MUL
0
helpful_count
int(11)
YES
0
created_at
datetime
YES
MUL
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_relations;
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
product_id
bigint(20) unsigned
NO
MUL
NULL
related_product_id
bigint(20) unsigned
NO
MUL
NULL
relation_type
enum('related','upsell','cross_sell','alternative','accessory')
NO
MUL
NULL
sort_order
int(11)
YES
0
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_questions;
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
product_id
bigint(20) unsigned
NO
MUL
NULL
user_id
int(11)
NO
MUL
NULL
question
text
NO
NULL
is_approved
tinyint(1)
YES
MUL
0
helpful_count
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
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_pricing;
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
auto_increment
product_id
bigint(20)
YES
MUL
NULL
variant_id
bigint(20)
YES
MUL
NULL
price
decimal(15,2)
NO
NULL
tax_rate
decimal(5,2)
YES
NULL
cost_price
decimal(15,2)
YES
NULL
compare_at_price
decimal(15,2)
YES
NULL
currency_code
char(3)
NO
MUL
NULL
pricing_type
enum('fixed','discount','auction','service')
YES
fixed
start_at
datetime
YES
NULL
end_at
datetime
YES
NULL
country_id
bigint(20)
YES
NULL
city_id
bigint(20)
YES
NULL
is_active
tinyint(1)
YES
MUL
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
DESCRIBE product_physical_attributes;
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
auto_increment
product_id
bigint(20) unsigned
NO
UNI
NULL
variant_id
bigint(20) unsigned
YES
UNI
NULL
weight
decimal(10,3)
YES
NULL
length
decimal(10,2)
YES
NULL
width
decimal(10,2)
YES
NULL
height
decimal(10,2)
YES
NULL
weight_unit
enum('kg','g','lb')
NO
kg
dimension_unit
enum('cm','mm','in')
NO
cm
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
DESCRIBE product_categories;
[ Edit inline ] [ Edit ] [ Create PHP code ]
Field
Type
Null
Key
Default
Extra
id
int(11)
NO
PRI
NULL
auto_increment
product_id
bigint(20)
NO
MUL
NULL
category_id
bigint(20)
NO
MUL
NULL
is_primary
tinyint(1)
YES
MUL
0
sort_order
int(11)
YES
0
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_attribute_values;
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
auto_increment
attribute_id
bigint(20)
NO
MUL
NULL
value
varchar(255)
NO
NULL
slug
varchar(255)
NO
MUL
NULL
sort_order
int(11)
YES
0
is_active
tinyint(1)
YES
1
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_attribute_value_translations;
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
auto_increment
attribute_value_id
bigint(20)
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
label
varchar(255)
NO
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_attributes;
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
slug
varchar(100)
NO
UNI
NULL
attribute_type_id
int(10) unsigned
NO
MUL
NULL
is_filterable
tinyint(1)
YES
MUL
1
is_visible
tinyint(1)
YES
1
is_required
tinyint(1)
YES
0
is_variation
tinyint(1)
YES
MUL
0
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
is_global
tinyint(1)
YES
1
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_attribute_translations;
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
auto_increment
attribute_id
bigint(20)
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
name
varchar(255)
NO
NULL
description
text
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_attribute_assignments;
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
auto_increment
product_id
bigint(20)
NO
MUL
NULL
attribute_id
bigint(20)
NO
MUL
NULL
attribute_value_id
bigint(20)
YES
MUL
NULL
custom_value
varchar(255)
YES
NULL
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
DESCRIBE product_answers;
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
question_id
bigint(20)
NO
MUL
NULL
user_id
int(11)
NO
MUL
NULL
answer
text
NO
NULL
is_approved
tinyint(1)
YES
MUL
0
is_staff_answer
tinyint(1)
YES
0
helpful_count
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
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_comparisons;
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
user_id
int(11)
NO
MUL
NULL
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_comparison_items;
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
comparison_id
bigint(20) unsigned
NO
MUL
NULL
product_id
bigint(20) unsigned
NO
MUL
NULL
added_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_bundles;
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
bigint(20) unsigned
NO
MUL
NULL
entity_id
bigint(20) unsigned
NO
MUL
NULL
bundle_name
varchar(255)
NO
NULL
description
text
YES
NULL
bundle_image
varchar(500)
YES
NULL
original_total_price
decimal(15,2)
NO
NULL
bundle_price
decimal(15,2)
NO
NULL
discount_amount
decimal(15,2)
NO
NULL
discount_percentage
decimal(5,2)
YES
NULL
stock_quantity
int(11)
YES
0
is_active
tinyint(1)
YES
MUL
1
start_date
datetime
YES
MUL
NULL
end_date
datetime
YES
NULL
sold_count
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
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_bundle_items;
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
bigint(20) unsigned
NO
MUL
NULL
bundle_id
bigint(20) unsigned
NO
MUL
NULL
product_id
bigint(20) unsigned
NO
MUL
NULL
quantity
int(11)
YES
1
product_price
decimal(15,2)
NO
NULL
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE recently_viewed_products;
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
user_id
int(11)
YES
MUL
NULL
session_id
varchar(255)
YES
MUL
NULL
product_id
bigint(20)
NO
MUL
NULL
viewed_at
datetime
YES
MUL
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_reviews;
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
auto_increment
product_id
bigint(20) unsigned
NO
MUL
NULL
user_id
int(11) unsigned
NO
MUL
NULL
rating
tinyint(4)
NO
MUL
NULL
title
varchar(255)
YES
NULL
comment
text
YES
NULL
is_verified_purchase
tinyint(1)
YES
0
is_approved
tinyint(1)
YES
MUL
0
helpful_count
int(11)
YES
0
created_at
datetime
YES
MUL
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_relations;
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
product_id
bigint(20) unsigned
NO
MUL
NULL
related_product_id
bigint(20) unsigned
NO
MUL
NULL
relation_type
enum('related','upsell','cross_sell','alternative','accessory')
NO
MUL
NULL
sort_order
int(11)
YES
0
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE product_questions;
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
product_id
bigint(20) unsigned
NO
MUL
NULL
user_id
int(11)
NO
MUL
NULL
question
text
NO
NULL
is_approved
tinyint(1)
YES
MUL
0
helpful_count
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
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE categories;
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
YES
MUL
NULL
parent_id
bigint(20)
YES
MUL
NULL
slug
varchar(255)
NO
UNI
NULL
name
varchar(255)
NO
NULL
description
text
YES
NULL
sort_order
int(11)
YES
MUL
0
is_active
tinyint(1)
YES
MUL
1
is_featured
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
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE category_translations;
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
auto_increment
category_id
bigint(20)
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
name
varchar(255)
NO
MUL
NULL
description
text
YES
NULL
slug
varchar(255)
YES
NULL
created_at
datetime
YES
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
meta_title
varchar(255)
YES
NULL
meta_description
text
YES
NULL
meta_keywords
varchar(500)
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE category_attributes;
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
category_id
bigint(20)
NO
MUL
NULL
attribute_id
bigint(20)
NO
MUL
NULL
is_required
tinyint(1)
YES
0
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
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE category_attribute_translations;
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
category_attribute_id
bigint(20)
NO
MUL
NULL
language_code
varchar(8)
NO
MUL
NULL
name
varchar(255)
NO
NULL
description
text
YES
NULL
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
DESCRIBE orders;
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
entity_id
bigint(20) unsigned
NO
MUL
NULL
order_number
varchar(100)
NO
UNI
NULL
user_id
int(11) unsigned
NO
MUL
NULL
cart_id
bigint(20) unsigned
YES
MUL
NULL
order_type
enum('online','pos','phone','wholesale')
YES
online
status
enum('pending','confirmed','processing','shipped','out_for_delivery','delivered','completed','cancelled','refunded','failed')
YES
MUL
pending
payment_status
enum('pending','paid','partial','failed','refunded')
YES
MUL
pending
fulfillment_status
enum('unfulfilled','partial','fulfilled')
YES
unfulfilled
subtotal
decimal(15,2)
NO
NULL
tax_amount
decimal(15,2)
YES
0.00
shipping_cost
decimal(15,2)
YES
0.00
discount_amount
decimal(15,2)
YES
0.00
coupon_discount
decimal(15,2)
YES
0.00
loyalty_points_discount
decimal(15,2)
YES
0.00
wallet_amount_used
decimal(15,2)
YES
0.00
total_amount
decimal(15,2)
NO
NULL
grand_total
decimal(15,2)
NO
NULL
currency_code
varchar(8)
YES
SAR
coupon_code
varchar(100)
YES
NULL
loyalty_points_used
int(11)
YES
0
loyalty_points_earned
int(11)
YES
0
shipping_address_id
int(11)
YES
MUL
NULL
billing_address_id
int(11)
YES
MUL
NULL
delivery_company_id
bigint(20)
YES
NULL
estimated_delivery_date
date
YES
NULL
actual_delivery_date
datetime
YES
NULL
customer_notes
text
YES
NULL
internal_notes
text
YES
NULL
ip_address
varchar(45)
YES
NULL
user_agent
text
YES
NULL
is_gift
tinyint(1)
YES
0
gift_message
text
YES
NULL
created_at
datetime
YES
MUL
current_timestamp()
updated_at
datetime
YES
current_timestamp()
on update current_timestamp()
confirmed_at
datetime
YES
NULL
shipped_at
datetime
YES
NULL
delivered_at
datetime
YES
NULL
cancelled_at
datetime
YES
NULL
cancellation_reason
text
YES
NULL
assigned_driver_id
bigint(20)
YES
MUL
NULL
pos_session_id
bigint(20)
YES
NULL
cashier_user_id
int(11)
YES
NULL
branch_entity_id
bigint(20)
YES
MUL
NULL
sales_channel
enum('online','pos','mobile_app','call_center','marketplace')
YES
online
payment_method
varchar(50)
YES
cash
delivery_entity_id
bigint(20) unsigned
YES
MUL
NULL
delivery_zone_id
bigint(20) unsigned
YES
NULL
auction_id
bigint(20) unsigned
YES
MUL
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE order_items;
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
NULL
order_id
bigint(20) unsigned
NO
MUL
NULL
entity_id
bigint(20) unsigned
NO
MUL
NULL
product_id
bigint(20)
NO
MUL
NULL
product_variant_id
bigint(20)
YES
NULL
product_name
varchar(500)
NO
NULL
sku
varchar(100)
NO
NULL
quantity
int(11)
NO
NULL
unit_price
decimal(15,2)
NO
NULL
sale_price
decimal(15,2)
YES
NULL
discount_amount
decimal(15,2)
YES
0.00
tax_rate
decimal(5,2)
YES
0.00
tax_amount
decimal(15,2)
YES
0.00
subtotal
decimal(15,2)
NO
NULL
total
decimal(15,2)
NO
NULL
currency_code
varchar(8)
YES
SAR
commission_rate
decimal(5,2)
YES
0.00
commission_amount
decimal(15,2)
YES
0.00
selected_attributes
text
YES
NULL
special_instructions
text
YES
NULL
is_refunded
tinyint(1)
YES
0
refunded_quantity
int(11)
YES
0
refunded_amount
decimal(15,2)
YES
0.00
created_at
datetime
YES
current_timestamp()
inventory_entity_id
bigint(20)
YES
NULL
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE order_status_history;
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
order_id
bigint(20)
NO
MUL
NULL
status
enum('pending','confirmed','processing','shipped','out_for_delivery','delivered','completed','cancelled','refunded','failed')
NO
MUL
NULL
notes
text
YES
NULL
notified_customer
tinyint(1)
YES
0
changed_by
int(11)
YES
MUL
NULL
ip_address
varchar(45)
YES
NULL
created_at
datetime
YES
MUL
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE order_reviews;
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
order_id
bigint(20)
NO
MUL
NULL
user_id
int(11)
NO
MUL
NULL
vendor_id
bigint(20)
NO
MUL
NULL
delivery_company_id
bigint(20)
YES
MUL
NULL
overall_rating
tinyint(4)
NO
MUL
NULL
product_quality_rating
tinyint(4)
YES
NULL
delivery_rating
tinyint(4)
YES
NULL
service_rating
tinyint(4)
YES
NULL
comment
text
YES
NULL
is_approved
tinyint(1)
YES
0
created_at
datetime
YES
current_timestamp()
Query results operations
  
 Current selection does not contain a unique column. Grid edit, checkbox, Edit, Copy and Delete features are not available. Documentation
Your SQL query has been executed successfully.
DESCRIBE entities;
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
parent_id
bigint(20) unsigned
YES
MUL
NULL
tenant_id
int(10) unsigned
NO
MUL
NULL
branch_code
varchar(50)
YES
NULL
user_id
int(11) unsigned
NO
MUL
NULL
store_name
varchar(255)
NO
NULL
slug
varchar(255)
NO
UNI
NULL
vendor_type
enum('product_seller','service_provider','both')
YES
product_seller
store_type
enum('individual','company','brand')
YES
individual
registration_number
varchar(100)
YES
NULL
tax_number
varchar(100)
YES
NULL
phone
varchar(45)
NO
NULL
mobile
varchar(45)
YES
NULL
email
varchar(191)
NO
NULL
website_url
varchar(500)
YES
NULL
timezone_id
int(10) unsigned
YES
MUL
NULL
status
enum('pending','approved','suspended','rejected')
YES
MUL
pending
suspension_reason
text
YES
NULL
is_verified
tinyint(1)
YES
0
joined_at
datetime
YES
current_timestamp()
approved_at
datetime
YES
NULL
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
  
Open new phpMyAdmin window