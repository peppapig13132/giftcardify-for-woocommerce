<?php

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();
$table_name_giftcards = $wpdb->prefix . 'giftcardify_gift_cards';
$table_name_giftcard_logs = $wpdb->prefix . 'giftcardify_gift_card_logs';

$sql_gift_cards  = "CREATE TABLE IF NOT EXISTS $table_name_giftcards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  receiver_firstname varchar(255) NOT NULL,
  receiver_lastname varchar(255) NOT NULL,
  receiver_email varchar(255) NOT NULL,
  sender_name varchar(255) NOT NULL,
  sender_email varchar(255) DEFAULT NULL,
  gift_message text,
  gift_card_code varchar(100) NOT NULL,
  gift_card_amount decimal(10,2) NOT NULL,
  gift_card_balance decimal(10,2) NOT NULL,
  gift_card_status ENUM('created', 'sent', 'used', 'expired') NOT NULL,
  shipping_at datetime DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at datetime DEFAULT NULL,
  expired_at datetime DEFAULT NULL
) $charset_collate;";

$sql_gift_card_logs = "CREATE TABLE IF NOT EXISTS $table_name_giftcard_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  gift_card_id INT NOT NULL,
  product_order_id INT NOT NULL,
  amount INT NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) $charset_collate;";