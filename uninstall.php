<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
  exit;
}

/**
 * Drop plugin tables only when explicitly enabled (e.g. in wp-config.php).
 * define( 'GIFTCARDIFY_DELETE_DATA', true );
 */
if (!defined('GIFTCARDIFY_DELETE_DATA') || !GIFTCARDIFY_DELETE_DATA) {
  return;
}

global $wpdb;

$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'giftcardify_gift_card_logs');
$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'giftcardify_gift_cards');

wp_clear_scheduled_hook('giftcardify_custom_cron_hook');
