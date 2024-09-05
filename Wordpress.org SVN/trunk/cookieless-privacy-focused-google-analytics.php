<?php
/**
 * Plugin Name: Cookieless Privacy-Focused Google Analytics
 * Description: Enables Google Analytics without setting cookies or storing any data in the browser. Asking for user consent in the frontend should not be necessary.
 * Version: 1.0.1
 * Author: Helge Klein
 * Author URI: https://helgeklein.com
 * License: GPL2
 */
 
/*  Copyright 2020 Helge Klein  (email: info@helgeklein.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace HK\CookielessGoogleAnalytics;

// Prevent direct access (outside of WordPress)
if (! defined('ABSPATH'))
{
   return;
}

class CookielessGoogleAnalytics
{
   // Plugin defaults
   private $defaultSettings = array(
      'gatrackingcode'     => '',
      'validityperiod'     => 4,
      'enableforadmins'    => ''
   );


   // Admin settings
   private $slug        = 'cookieless_privacy_focused_google_analytics';
   private $capability  = 'manage_options';

   //
   // Constructor
   //
   public function __construct()
   {
      //
      // Admin UI
      //
      // Hook into the admin menu
      add_action ('admin_menu', array($this, 'AdmCreatePluginSettingsPage'));

      // Register settings sections
      add_action ('admin_init', array($this, 'AdmSetupSections'));
      
      // Register settings fields
      add_action ('admin_init', array($this, 'AdmSetupFields'));

      // Add a link to the plugin's settings on the plugins page
      add_filter ('plugin_action_links', array($this, 'AdmAddActionLinks'), 10, 5);

      //
      // Plugin functionality
      //
      add_action ('wp_head', array($this, 'AddJavaScriptToPage'));
   }
      
   ///////////////////////////////////////////////////////////////////
   //
   // Admin menu
   //
   ///////////////////////////////////////////////////////////////////

   //
   // Add a link to the plugin's settings on the plugins page
   // Source: https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
   //
   function AdmAddActionLinks ($actions, $plugin_file)
   {
      static $plugin;

      if (!isset($plugin))
         $plugin = plugin_basename (__FILE__);

      if ($plugin == $plugin_file)
      {
         $settings = array ('settings' => '<a href="options-general.php?page=' . $this->slug . '">Settings</a>');
      
         $actions = array_merge ($settings, $actions);
      }
      
      return $actions;
   }


   //
   // Create the settings page as a submenu of general settings
   //
   public function AdmCreatePluginSettingsPage ()
   {
      $page_title = 'Cookieless Privacy-Focused Google Analytics';
      $menu_title = 'Cookieless Google Analytics';
      $callback   = array($this, 'AdmPluginSettingsPageHtml');

      add_options_page ($page_title, $menu_title, $this->capability, $this->slug, $callback);
   }

   //
   // Output the settings page's content
   //
   public function AdmPluginSettingsPageHtml ()
   {
      // Check user capabilities
      if (!current_user_can($this->capability))
      {
         return;
      }
      ?>

      <div class="wrap">
         <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
         <form action="options.php" method="post">
            <?php
               settings_fields ($this->slug);
               do_settings_sections ($this->slug);
               submit_button ();
            ?>

         </form>
      </div>

      <?php

   }

   //
   // Set up the sections for the settings page
   //
   public function AdmSetupSections ()
   {
      add_settings_section ('cpfga_section_main', 'Main Settings', array($this, 'AdmSectionCallback'), $this->slug);
      add_settings_section ('cpfga_section_adv', 'Advanced Settings', array($this, 'AdmSectionCallback'), $this->slug);
   }

   //
   // Section callback
   //
   public function AdmSectionCallback ($arguments)
   {
      switch ($arguments['id'])
      {
         case 'cpfga_section_main':
            echo 'Please configure these settings before using the plugin.';
            break;
         case 'cpfga_section_adv':
            echo 'These settings rarely need to be modified.';
            break;
      }
   }

   //
   // Set up the fields for the settings page
   //
   public function AdmSetupFields ()
   {
      $fields = array(
         array
         (
            'uid' => 'gatrackingcode',
            'label' => 'Google Analytics tracking code:',
            'section' => 'cpfga_section_main',
            'type' => 'text',
            'placeholder' => 'UA-xxxxxx-y',
            'helper' => '',
            'supplemental' => '',
            'default' => $this->defaultSettings['gatrackingcode']
         ),
         array
         (
            'uid' => 'validityperiod',
            'label' => 'Validity period:',
            'section' => 'cpfga_section_adv',
            'type' => 'number',
            'input_options' => 'step="0.1"',
            'placeholder' => '',
            'helper' => '',
            'supplemental' => 'Number of stays before the hash changes. A shorter interval improves privacy but makes session tracking more unreliable.',
            'default' => $this->defaultSettings['validityperiod']
         ),
         array
         (
            'uid' => 'enableforadmins',
            'label' => 'Enable for admins:',
            'section' => 'cpfga_section_adv',
            'type' => 'checkbox',
            'helper' => '',
            'supplemental' => 'Add the data collection script for users with admin privileges (WordPress capability manage_options)?',
            'default' => $this->defaultSettings['enableforadmins']
         )
      );
      
      foreach ($fields as $field)
      {
         add_settings_field ($field['uid'], $field['label'], array($this, 'AdmFieldCallback'), $this->slug, $field['section'], $field);
         register_setting ($this->slug, $field['uid']);
      }
   }

   //
   // Field callback
   //
   public function AdmFieldCallback ($arguments)
   {
      // Get the current value, if there is one
      $value = get_option ($arguments['uid']);
      if (! $value)
      { 
         // No value exists -> set to our default
         $value = $arguments['default'];
      }

      $inputOptions = '';
      if (! empty($arguments['input_options']))
      {
         $inputOptions = $arguments['input_options'];
      }

      // Check which type of field we want
      switch ($arguments['type'])
      {
         case 'text':
         case 'password':
         case 'number':
            printf('<input name="%1$s" id="%1$s" type="%2$s" placeholder="%3$s" value="%4$s" %5$s />', $arguments['uid'], $arguments['type'], $arguments['placeholder'], $value, $inputOptions);
            break;
         case 'textarea':
            printf('<textarea name="%1$s" id="%1$s" placeholder="%2$s" rows="5" cols="50">%3$s</textarea>', $arguments['uid'], $arguments['placeholder'], $value);
            break;
         case 'checkbox':
            printf('<input name="%1$s" id="%1$s" type="%2$s" value="1" %3$s />', $arguments['uid'], $arguments['type'], $value == '1' ? 'checked' : '');
            break;
         case 'select':
         case 'multiselect':
            if (! empty($arguments['options']) && is_array($arguments['options']))
            {
               $attributes = '';
               $options_markup = '';
               foreach ($arguments['options'] as $key => $label)
               {
                  $options_markup .= sprintf ('<option value="%s" %s>%s</option>', $key, selected($value[array_search($key, $value, true)], $key, false), $label);
               }
               if ($arguments['type'] === 'multiselect')
               {
                  $attributes = ' multiple="multiple" ';
               }
               printf ('<select name="%1$s[]" id="%1$s" %2$s>%3$s</select>', $arguments['uid'], $attributes, $options_markup);
            }
            break;
      }

      // If there is help text
      if (!@empty($arguments['helper']))
      {
         printf ('<span class="helper"> %s</span>', $arguments['helper']);
      }

      // If there is supplemental text
      if (!@empty($arguments['supplemental']))
      {
         printf ('<p class="description">%s</p>', $arguments['supplemental']);
      }
   }

   ///////////////////////////////////////////////////////////////////
   //
   // Plugin functionality
   //
   ///////////////////////////////////////////////////////////////////

   //
   // Print the JavaScript code to be added to the page head
   //
   public function AddJavaScriptToPage ()
   {
      // Read plugin settings from DB (falling back to defaults)
      $settings = $this->GetDbSettings ();

      // Plugin configured?
      if (empty($settings['gatrackingcode']))
      {
         return;
      }

      // Ignore the backend (admin pages)
      if (is_admin())
         return;

      // Ignore admins on the frontend unless allowed by the configuration
      if (current_user_can('manage_options') && empty($settings['enableforadmins']))
         return;

      // Build the output string
      $output = <<<EndOfHeredoc

<script async src="https://www.googletagmanager.com/gtag/js?id={$settings['gatrackingcode']}">
</script>
<script>
const cyrb53 = function(str, seed = 0) {
   let h1 = 0xdeadbeef ^ seed,
      h2 = 0x41c6ce57 ^ seed;
   for (let i = 0, ch; i < str.length; i++) {
      ch = str.charCodeAt(i);
      h1 = Math.imul(h1 ^ ch, 2654435761);
      h2 = Math.imul(h2 ^ ch, 1597334677);
   }
   h1 = Math.imul(h1 ^ h1 >>> 16, 2246822507) ^ Math.imul(h2 ^ h2 >>> 13, 3266489909);
   h2 = Math.imul(h2 ^ h2 >>> 16, 2246822507) ^ Math.imul(h1 ^ h1 >>> 13, 3266489909);
   return 4294967296 * (2097151 & h2) + (h1 >>> 0);
};

let clientIP = "{$_SERVER['REMOTE_ADDR']}";
let validityInterval = Math.round (new Date() / 1000 / 3600 / 24 / {$settings['validityperiod']});
let clientIDSource = clientIP + ";" + window.location.host + ";" + navigator.userAgent + ";" + navigator.language + ";" + validityInterval;
let clientIDHashed = cyrb53(clientIDSource).toString(16);

window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$settings['gatrackingcode']}', {
    'anonymize_ip': true,
    'client_id': clientIDHashed
  });
  gtag('event', 'page_view');
</script>

EndOfHeredoc;

      echo $output;
   }

   //
   // Get stored options from the database
   //
   function GetDbSettings ()
   {
      foreach ($this->defaultSettings as $key => $defaultValue)
      {
         $dbValue = get_option ($key);
         if (!empty($dbValue))
            $dbSettings[$key] = $dbValue;
         else
            $dbSettings[$key] = $defaultValue;
      }
      
      return $dbSettings;
   }
}

// Instantiate a class object
$CookielessGoogleAnalytics = new CookielessGoogleAnalytics();

?>