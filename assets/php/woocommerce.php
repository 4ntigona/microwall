<?php
// Renderizar as configurações da seção "Assinaturas"
function microwall_render_subscription_settings() {
    // check if WooCommerce is installed
    $wcDir = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
    if (!file_exists($wcDir)) {

        echo '<h3>Instalar WooCommerce</h3>';
        echo '<p>O plugin Microwall é compatível com o WooCommerce. Por favor, instale e ative o WooCommerce para utilizar esta funcionalidade.</p>';
        echo '<button id="microwall-install-woocommerce" class="button-primary">Instalar WooCommerce</button>';

        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#microwall-install-woocommerce').on('click', function() {
                $(this).attr('disabled', 'disabled').text('Instalando...');
                var data = {
                    action: 'microwall_install_woocommerce'
                };

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response === 'success') {
                            location.reload();
                        } else {
                            console.error(response);
                            alert('Ocorreu um erro ao instalar o WooCommerce. Por favor, verifique os logs do WordPress para mais detalhes.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(xhr.responseText);
                        alert('Ocorreu um erro ao instalar o WooCommerce. Por favor, verifique os logs do WordPress para mais detalhes.');
                    }
                });
            });
        });
        </script>
        <?php

    } else {


        // check if WooCommerce is active
        $is_woocommerce_active = in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));

        if ($is_woocommerce_active) {
            // Obtenha todos os produtos do WooCommerce
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
            );
            $products = new WP_Query($args);

            $selected_products = get_option('microwall_selected_products', array());

            echo '<h3>Produtos / Assinaturas</h3>';

            echo '<form method="post" action="">';
            echo '<div class="form_field">';
            echo '<p>Selecione os produtos que serão considerados para assinatura:</p>';
            echo '<select name="microwall_selected_products[]" class="microwall-product-select" multiple="multiple">';
            
            while ($products->have_posts()) {
                $products->the_post();
                $product_id = get_the_ID();
                $selected = in_array($product_id, $selected_products) ? 'selected="selected"' : '';
                echo '<option value="' . $product_id . '" ' . $selected . '>' . get_the_title() . '</option>';
            }
            
            echo '</select>';
            echo '</div>';
            
            echo '<div class="form_field">';
            echo '<button type="submit" class="button-primary">Salvar</button>';
            echo '</div>';
            echo '</form>';

            ?>
            <script>
            jQuery(document).ready(function($) {
                $('.microwall-product-select').select2();
            });
            </script>
            <?php
        } else {

            echo '<h3>Ativar WooCommerce</h3>';
            echo '<p>O plugin Microwall é compatível com o WooCommerce. Ele já está instalado no seu site, mas ainda não está ativo.</p>';
            echo '<button id="microwall-activate-woocommerce" class="button-primary">Ativar WooCommerce</button>';

            ?>
            <script>
            jQuery(document).ready(function($) {
                $('#microwall-activate-woocommerce').on('click', function() {
                    $(this).attr('disabled', 'disabled').text('Instalando...');
                    var data = {
                        action: 'microwall_activate_woocommerce'
                    };

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: data,
                        success: function(response) {
                            if (response === 'success') {
                                location.reload();
                            } else {
                                console.error(response);
                                alert('Ocorreu um erro ao ativar o WooCommerce. Por favor, verifique os logs do WordPress para mais detalhes.');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error(xhr.responseText);
                            alert('Ocorreu um erro ao ativar o WooCommerce. Por favor, verifique os logs do WordPress para mais detalhes.');
                        }
                    });
                });
            });
            </script>
            <?php
        }
    
    }
}

function microwall_install_woocommerce() {
    if (current_user_can('install_plugins')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
        include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');

        $api = plugins_api('plugin_information', array('slug' => 'woocommerce'));
        $upgrader = new Plugin_Upgrader();
        $install = $upgrader->install($api->download_link);

        if ($install === true) {
            activate_plugin('woocommerce/woocommerce.php');
            echo 'success';
        } else {
            echo $install->get_error_message();
        }
    } else {
        echo 'Você não tem permissão para instalar plugins.';
    }
    wp_die();
}
add_action('wp_ajax_microwall_install_woocommerce', 'microwall_install_woocommerce');

function microwall_activate_woocommerce() {
    if (current_user_can('activate_plugins')) {
        activate_plugin('woocommerce/woocommerce.php');
        echo 'success';
    } else {
        echo 'Você não tem permissão para ativar plugins.';
    }
    wp_die();
}
add_action('wp_ajax_microwall_activate_woocommerce', 'microwall_activate_woocommerce');



// Verificar os produtos selecionados e atualizar a data de assinatura
function microwall_verify_selected_products($user_id) {
    $selected_products = get_option('microwall_selected_products', array());

    foreach ($selected_products as $product_id) {
        $product = wc_get_product($product_id);

        if ($product) {
            $subscription_duration = get_post_meta($product->get_id(), 'Duração da assinatura', true);
            
            if ($subscription_duration) {
                $start_date = current_time('mysql');
                $end_date = microwall_calculate_end_date($subscription_duration, $start_date);
                
                update_user_meta($user_id, 'microwall_start_date', $start_date);
                update_user_meta($user_id, 'microwall_end_date', $end_date);
                update_user_meta($user_id, 'microwall_subscription_status', 'Ativa');
                
                $user = new WP_User($user_id);
                $user->add_role('subscriber');
            }
        }
    }
}

// Verificar os usuários com base nos produtos selecionados ao ativar o plugin
function microwall_check_existing_users() {
    $selected_products = get_option('microwall_selected_products', array());

    if (!empty($selected_products)) {
        $customers = get_users(array(
            'role' => 'customer',
        ));

        foreach ($customers as $customer) {
            $customer_orders = wc_get_orders(array(
                'customer_id' => $customer->ID,
                'status' => array('completed', 'processing'),
            ));

            foreach ($customer_orders as $order) {
                $order_items = $order->get_items();

                foreach ($order_items as $item) {
                    $product_id = $item->get_product_id();

                    if (in_array($product_id, $selected_products)) {
                        microwall_verify_selected_products($customer->ID);
                        break; // Para evitar repetição de verificação para o mesmo usuário
                    }
                }
            }
        }
    }
}
add_action('admin_init', 'microwall_check_existing_users');

// Verificar os produtos selecionados em cada nova compra
function microwall_check_new_purchase($order_id) {
    $selected_products = get_option('microwall_selected_products', array());
    $order = wc_get_order($order_id);

    if ($order) {
        $order_items = $order->get_items();

        foreach ($order_items as $item) {
            $product_id = $item->get_product_id();

            if (in_array($product_id, $selected_products)) {
                $customer_id = $order->get_customer_id();
                microwall_verify_selected_products($customer_id);
                break; // Para evitar repetição de verificação para o mesmo usuário
            }
        }
    }
}
add_action('woocommerce_payment_complete', 'microwall_check_new_purchase');

// Adicionar campo "Duração da Assinatura" na página de edição de produtos do WooCommerce
function microwall_add_subscription_duration() {
    global $post;

    // Verifica se o post é um produto do WooCommerce
    if ($post && $post->post_type === 'product' && function_exists('woocommerce_wp_text_input')) {
        woocommerce_wp_text_input(array(
            'id'          => 'microwall_subscription_duration',
            'label'       => 'Duração da Assinatura',
            'desc_tip'    => true,
            'description' => 'Informe a duração da assinatura para este produto',
            'type'        => 'number',
            'custom_attributes' => array(
                'step' => '1',
                'min'  => '0'
            )
        ));

        // Selecionar período (meses/anos)
        woocommerce_wp_select(array(
            'id'          => 'microwall_subscription_duration_period',
            'label'       => '',
            'desc_tip'    => true,
            'description' => 'Selecione o período da duração',
            'options'     => array(
                'months' => 'Meses',
                'years'  => 'Anos'
            )
        ));
    }
}
add_action('woocommerce_product_options_general_product_data', 'microwall_add_subscription_duration');

// Salvar o valor do campo "Duração da Assinatura"
function microwall_save_subscription_duration($product_id) {
    $subscription_duration = isset($_POST['microwall_subscription_duration']) ? absint($_POST['microwall_subscription_duration']) : '';
    $subscription_period = isset($_POST['microwall_subscription_duration_period']) ? sanitize_text_field($_POST['microwall_subscription_duration_period']) : '';

    // Salvar o valor do campo "Duração da Assinatura"
    if (!empty($subscription_duration)) {
        update_post_meta($product_id, 'microwall_subscription_duration', $subscription_duration);
    }

    // Salvar o valor do período (meses/anos)
    if (!empty($subscription_period)) {
        update_post_meta($product_id, 'microwall_subscription_duration_period', $subscription_period);
    }
}
add_action('woocommerce_process_product_meta', 'microwall_save_subscription_duration');

?>