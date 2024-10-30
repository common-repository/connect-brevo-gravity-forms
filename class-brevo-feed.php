<?php

if (! defined('ABSPATH')) {
    exit;
}

GFForms::include_feed_addon_framework();
class PCAFE_GFBR_Brevo_Free extends GFFeedAddOn {
    protected $_version                      = PCAFE_GFBR_VERSION_FREE;
    protected $_min_gravityforms_version    = '1.9.16';
    protected $_slug                         = 'brevo_addon_free';
    protected $_path                         = 'brevo-for-gravityforms/gf-brevo.php';
    protected $_full_path                    = __FILE__;
    protected $_title                       = 'Brevo For Gravity Forms';
    protected $_short_title                 = 'Brevo';
    protected $_multiple_feeds               = false;
    private static $_instance = null;

    protected $api = null;

    protected $_async_feed_processing = true;

    /**
     * Get an instance of this class.
     *
     * @return PCAFE_GFBR_Brevo_Free
     */
    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new PCAFE_GFBR_Brevo_Free();
        }

        return self::$_instance;
    }


    public function init() {
        parent::init();

        $this->add_delayed_payment_support(
            array(
                'option_label' => esc_html__('Add data to Brevo only when payment is received.', 'connect-brevo-gravity-forms'),
            )
        );
    }

    public function init_admin() {
        parent::init_admin();

        add_filter('gform_entry_detail_meta_boxes', array($this, 'brevo_created_profile_info'), 10, 3);
    }

    public function get_menu_icon() {
        return file_get_contents($this->get_base_path() . '/assets/images/brevo.svg'); //phpcs:ignore}
    }

    public function note_avatar() {
        return $this->get_base_url() . "/assets/images/brevo.svg";
    }

    public function plugin_settings_fields() {
        return array(
            array(
                'title'  => esc_html__('Brevo Add-On Settings', 'connect-brevo-gravity-forms'),
                'fields' => array(
                    array(
                        'name'      => 'brevo_api_key',
                        'label'     => esc_html__('Brevo API Key', 'connect-brevo-gravity-forms'),
                        'tooltip'   => esc_html__('Please enter your company name', 'connect-brevo-gravity-forms'),
                        'type'      => 'text',
                        'class'     => 'small',
                        'feedback_callback' => array($this, 'initialize_brevo_api'),
                    ),
                    array(
                        'type'     => 'save',
                        'messages' => array(
                            'success' => esc_html__('Brevo settings have been updated.', 'connect-brevo-gravity-forms')
                        ),
                    )
                )
            )
        );
    }

    public function initialize_brevo_api() {

        if (! is_null($this->api)) {
            return true;
        }

        $settings = $this->get_plugin_settings();

        if (! rgar($settings, 'brevo_api_key')) {
            return null;
        }

        $brevo = new PCAFE_GFBR_Api_Free($settings['brevo_api_key']);

        $account = $brevo->get_account();

        if (is_wp_error($account)) {
            return false;
        }

        $this->api = $brevo;

        return true;
    }

    public function feed_settings_fields() {
        return array(
            array(
                'title'  => esc_html__('Integration with Brevo', 'connect-brevo-gravity-forms'),
                'fields' => array(
                    array(
                        'label'     => esc_html__('Enable', 'connect-brevo-gravity-forms'),
                        'name'      => 'is_enable',
                        'type'      => 'toggle',
                        'default_value' => false,
                    ),
                    array(
                        'name'      => 'api_field',
                        'type'      => 'field_map',
                        'field_map' => array(
                            array(
                                'name'       => 'first_name',
                                'label'      => esc_html__('First Name', 'connect-brevo-gravity-forms'),
                                'required'   => false,
                                'tooltip'    => esc_html__('Please choose the first name', 'connect-brevo-gravity-forms'),
                            ),
                            array(
                                'name'       => 'email',
                                'label'      => esc_html__('Email Address', 'connect-brevo-gravity-forms'),
                                'required'   => true,
                                'field_type' => array('email'),
                                'tooltip'    => esc_html__('Please choose the email', 'connect-brevo-gravity-forms'),
                            ),
                        ),
                        'dependency' => array(
                            'live'     => true,
                            'fields' => array(
                                array(
                                    'field' => 'is_enable',
                                ),
                            ),
                        )
                    ),
                    array(
                        'name'       => 'brevo_list',
                        'label'      => esc_html__('Add to List', 'connect-brevo-gravity-forms'),
                        'type'       => 'select',
                        'required'   => true,
                        'choices'    => $this->get_lists_for_feed_settings(),
                        'dependency' => array(
                            'live'     => true,
                            'fields' => array(
                                array(
                                    'field' => 'is_enable',
                                ),
                            ),
                        )
                    ),
                )
            ),
            array(
                'title'  => esc_html__('Conditional logic', 'connect-brevo-gravity-forms'),
                'fields' => array(
                    array(
                        'type'           => 'feed_condition',
                        'name'           => 'feed-condition',
                        'label'          => esc_html__('Conditions', 'connect-brevo-gravity-forms'),
                        'checkbox_label' => esc_html__('Enable conditional processing', 'connect-brevo-gravity-forms')
                    ),
                ),
            )
        );
    }

    /**
     * Set feed creation control.
     *
     * @since  1.0
     *
     * @return bool
     */
    public function can_create_feed() {
        return $this->initialize_brevo_api();
    }

    public function get_lists_for_feed_settings() {
        if (! $this->initialize_brevo_api()) {
            return array();
        }

        $cache_name = 'pcafe_gfbr_' . $this->get_slug() . '_list_attr';
        $list_item = get_transient($cache_name);

        if ($list_item !== false) {
            return $list_item;
        }

        $choices = array(
            array(
                'label' => esc_html__('Select a List', 'connect-brevo-gravity-forms'),
                'value' => '',
            ),
        );

        $lists = $this->brevo_lists();

        foreach ($lists['lists'] as $list) {
            $choices[] = array(
                'label' => esc_html($list['name']),
                'value' => esc_attr($list['id'])
            );
        }

        set_transient($cache_name, $choices, 5 * MINUTE_IN_SECONDS);

        return $choices;
    }

    public function brevo_lists() {
        $lists = $this->api->get_lists();

        if (is_wp_error($lists)) {
            return $lists;
        }

        return $lists;
    }

    public function process_feed($feed, $entry, $form) {

        if (! $feed['meta']['is_enable']) {
            return $entry;
        }

        if (! $this->initialize_brevo_api()) {
            return $entry;
        }

        $contact = array(
            'email'     => $this->get_field_value($form, $entry, $feed['meta']['api_field_email']),
            'attributes' => array(
                'FIRSTNAME'      => $this->get_field_value($form, $entry, $feed['meta']['api_field_first_name']),
            ),
            'listIds'   => array(5),
            'smsBlacklisted' => false,
            'emailBlacklisted' => false
        );

        if (GFCommon::is_invalid_or_empty_email($contact['email'])) {
            $this->add_feed_error(esc_html__('Unable to subscribe user to list because an invalid or empty email address was provided.', 'connect-brevo-gravity-forms'), $feed, $entry, $form);
            return $entry;
        }

        $created_contact = $this->api->create_contact($contact);

        if (is_wp_error($created_contact)) {
            $error_message = $created_contact->get_error_message();

            // Log that contact could not be created.
            $this->add_feed_error(
                sprintf(
                    // translators: Placeholder represents error message.
                    esc_html__('Unable to create contact: %s', 'connect-brevo-gravity-forms'),
                    $error_message
                ),
                $feed,
                $entry,
                $form
            );
        } else {
            gform_update_meta($entry['id'], 'pcafe_gfbr_contact_id', $created_contact['id']);
            $this->add_note($entry['id'], 'Entry sent successfully to Brevo API.', 'success');
            // Log that contact was created.
            $this->log_debug(__METHOD__ . '(): Contact was created.');
        }

        return $entry;
    }

    /**
     * Add a meta box to the Gravity Forms Entry Detail page.
     *
     * @since 1.0.0
     *
     * @param array $meta_boxes List of meta boxes.
     * @param array $entry      Entry data.
     * @param array $form       Form data.
     *
     * @return array Modified list of meta boxes.
     */

    public function brevo_created_profile_info($meta_boxes, $entry, $form) {

        $meta_boxes['brevo_profile_id'] = array(
            'title'         => esc_html__('Brevo', 'connect-brevo-gravity-forms'),
            'callback'      => array($this, 'get_brevo_profile_id'),
            'context'       => 'side'
        );

        return $meta_boxes;
    }

    public function get_brevo_profile_id($args) {

        $meta_value = gform_get_meta($args['entry']['id'], 'pcafe_gfbr_contact_id');

        if ($meta_value) {
            printf(
                '<span class="success">%1$s</span>',
                /* translators: %s: Profile id */
                sprintf(esc_html__('New contact created : ID - %s', 'connect-brevo-gravity-forms'), esc_html($meta_value))
            );
        } else {
            printf(
                '<span class="no-data">%s</span>',
                esc_html__('No data.', 'connect-brevo-gravity-forms')
            );
        }
    }
}
