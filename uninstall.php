<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

// Only delete options; DO NOT drop tables by default (safety)
delete_option('luxvv_options');
