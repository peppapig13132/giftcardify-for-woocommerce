<?php

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();
$table_name_giftcards = $wpdb->prefix . 'giftcards';
$table_name_giftcard_logs = $wpdb->prefix . 'giftcard_logs';

$sql_giftcards  = "CREATE TABLE IF NOT EXISTS $table_name_giftcards (
  id mediumint(9) NOT NULL AUTO_INCREMENT,
  receiver_firstname varchar(255) NOT NULL,
  receiver_lastname varchar(255) NOT NULL,
  receiver_email varchar(255) NOT NULL,
  sender_name varchar(255) NOT NULL,
  sender_email varchar(255) DEFAULT NULL,
  gift_message text,
  code varchar(100) NOT NULL,
  amount decimal(10,2) NOT NULL,
  balance decimal(10,2) NOT NULL,
  shipping_at datetime DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT NULL,
  expired_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY code_unique (code)
  INDEX (code)
) $charset_collate;";

$sql_giftcard_logs = "CREATE TABLE IF NOT EXISTS $table_name_giftcard_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  giftcard_id INT NOT NULL,
  product_order_id INT NOT NULL,
  status ENUM('draft', 'pending', 'success', 'failed', 'canceled') NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT NULL,
  INDEX (giftcard_id),
  INDEX (product_order_id)
) $charset_collate;";