<?php

/*
    Copyright 2009-2012 Edward L. Platt <elplatt@alum.mit.edu>
    
    This file is part of the Seltzer CRM Project
    amazon_payment.inc.php - Amazon payments extensions for the payment module.

    Seltzer is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    any later version.

    Seltzer is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Seltzer.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * @return This module's revision number.  Each new release should increment
 * this number.
 */
function amazon_payment_revision () {
    return 1;
}

/**
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
 */
function amazon_payment_install($old_revision = 0) {
    // Create initial database table
    if ($old_revision < 1) {
        
        // Additional payment info for amazon payments
        $sql = '
            CREATE TABLE IF NOT EXISTS `payment_amazon` (
              `pmtid` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
              `amazon_name` varchar(255) NOT NULL,
              PRIMARY KEY (`pmtid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
        ';
        $res = mysql_query($sql);
        if (!$res) die(mysql_error());
        
        // Additional contact info for amazon payments
        $sql = '
            CREATE TABLE IF NOT EXISTS `contact_amazon` (
              `cid` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
              `amazon_name` varchar(255) NOT NULL,
              PRIMARY KEY (`cid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
        ';
        $res = mysql_query($sql);
        if (!$res) die(mysql_error());
    }
}


/**
 * Page hook.  Adds module content to a page before it is rendered.
 *
 * @param &$page_data Reference to data about the page being rendered.
 * @param $page_name The name of the page being rendered.
 * @param $options The array of options passed to theme('page').
*/
function amazon_payment_page (&$page_data, $page_name, $options) {
    switch ($page_name) {
        case 'payments':
            if (user_access('payment_add')) {
                page_add_content_top($page_data, theme('form', amazon_payment_import_form()), 'Import');                
            }
            break;
    }
}

/**
 * @return an amazon payments import form structure.
 */
function amazon_payment_import_form () {
    return array(
        'type' => 'form'
        , 'method' => 'post'
        , 'enctype' => 'multipart/form-data'
        , 'command' => 'amazon_payment_import'
        , 'fields' => array(
            array(
                'type' => 'message'
                , 'value' => '<p>Use this page to upload amazon payments data in comma-separated (CSV) format.</p>'
            )
            , array(
                'type' => 'file'
                , 'label' => 'CSV File'
                , 'name' => 'payment-file'
            )
            , array(
                'type' => 'submit'
                , 'value' => 'Import'
            )
        )
    );
}

/**
 * Handle amazon payment import request.
 *
 * @return The url to display on completion.
 */
function command_amazon_payment_import () {
    
    if (!user_access('payment_add')) {
        error_register('User does not have permission: payment_add');
        return 'index.php?q=payments';
    }
    
    if (!array_key_exists('payment-file', $_FILES)) {
        error_register('No payment file uploaded');
        return 'index.php?q=payments&tab=import';
    }
    
    $csv = file_get_contents($_FILES['payment-file']['tmp_name']);
    $data = csv_parse($csv);
    
    $count = 0;
    foreach ($data as $row) {
        
        // Ignore withdrawals, holds, and failures
        if ($row['Type'] !== 'Payment') {
            continue;
        }
        if ($row['To/From'] !== 'From') {
            continue;
        }
        if ($row['Status'] !== 'Completed') {
            continue;
        }
        
        // Create payment object and save
        $payment = array(
            'date' => date('Y-m-d', strtotime($row['Date']))
            , 'code' => 'USD'
            , 'amount' => payment_normalize_currency($row['Amount'])
            , 'method' => 'amazon'
            , 'confirmation' => $row['Transaction ID']
            , 'notes' => $row['notes']
        );
        $payment = payment_save($payment);
        $count++;
    }
    
    message_register("Successfully imported $count payment(s)");
    
    return 'index.php?q=payments';
}
