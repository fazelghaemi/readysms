<?php
// File: admin-settings.php
// این فایل مربوط به بخش تنظیمات پیشخوان (ادمین) افزونه Readysms است.
// شامل تنظیمات ورود با پیامک و گوگل، راهنماهای استفاده و بخش تست API (ارسال، وضعیت، قالب و موجودی) می‌باشد.

if ( ! defined('ABSPATH') ) {
    exit;
}

/**
 * بارگذاری استایل‌ها و اسکریپت‌های ادمین، به همراه Toastr برای اعلان‌های toast
 */
function ready_login_enqueue_styles() {
    wp_enqueue_style('readysms-style', plugin_dir_url(dirname(__FILE__)) . 'assets/css/panel.css', array(), '1.0.0');
    wp_enqueue_style('toastr-css', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css', array(), '2.1.4');
}

function ready_login_enqueue_admin_scripts() {
    wp_enqueue_script('readysms-admin-js', plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin-settings.js', array('jquery'), '1.0.0', true);
    wp_enqueue_script('toastr-js', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', array('jquery'), '2.1.4', true);
    wp_localize_script('readysms-admin-js', 'readyLoginAdminAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('readysms-admin-nonce'),
    ));
}
add_action('admin_enqueue_scripts', 'ready_login_enqueue_admin_scripts');
add_action('admin_enqueue_scripts', 'ready_login_enqueue_styles');

/**
 * افزودن منوی اصلی و زیرمنوها در پیشخوان وردپرس
 */
function ready_login_add_admin_menu() {
    add_menu_page(
        'تنظیمات ردی اس‌ام‌اس',            // عنوان صفحه تنظیمات
        'ردی اس‌ام‌اس',                    // عنوان منو به فارسی
        'manage_options',
        'readysms-settings',
        'ready_login_render_dashboard',
        'dashicons-admin-users'
    );
    
    add_submenu_page(
        'readysms-settings',
        'تنظیمات ورود با گوگل',
        'ورود با گوگل',
        'manage_options',
        'readysms-google-settings',
        'ready_login_render_google_settings'
    );
    
    add_submenu_page(
        'readysms-settings',
        'تنظیمات پیامک',
        'ورود با پیامک',
        'manage_options',
        'readysms-sms-settings',
        'ready_login_render_sms_settings'
    );
}
add_action('admin_menu', 'ready_login_add_admin_menu');

/**
 * ثبت تنظیمات
 */
function ready_login_register_settings() {
    // تنظیمات پیامک (راه‌پیام)
    register_setting('readysms-sms-options', 'ready_sms_api_key', 'sanitize_text_field');
    register_setting('readysms-sms-options', 'ready_sms_number', 'sanitize_text_field');
    register_setting('readysms-sms-options', 'ready_sms_pattern_code', 'sanitize_text_field');
    // تنظیمات ورود با گوگل
    register_setting('ready-google-options', 'ready_google_client_id', 'sanitize_text_field');
    register_setting('ready-google-options', 'ready_google_client_secret', 'sanitize_text_field');
}
add_action('admin_init', 'ready_login_register_settings');

/**
 * نمایش نوتیفیکیشن پس از ذخیره تنظیمات
 */
function ready_login_admin_notices() {
    if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true' ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('تنظیمات با موفقیت ذخیره شد.', 'readysms') . '</p></div>';
    }
}
add_action('admin_notices', 'ready_login_admin_notices');

/**
 * صفحه داشبورد اصلی افزونه (پیشخوان)
 * شامل راهنمای استفاده از پلاگین، راهنمای گوگل، خلاصه‌ای از استفاده از سامانه راه پیام و بخش تست API می‌باشد.
 */
function ready_login_render_dashboard() {
    ?>
    <div class="wrap">
        <!-- بنر افزونه با لینک وبلاگ -->
        <div style="text-align:center; margin-bottom:20px;">
            <a href="https://blog.msgway.com/contact/" target="_blank">
                <img src="<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/banner.jpg'; ?>" style="max-width:100%; height:auto; border-radius:12px;" alt="بنر ردی اس‌ام‌اس">
            </a>
        </div>

        <h1>تنظیمات ردی اس‌ام‌اس</h1>
        <div class="dokme-container" style="margin-bottom:20px;">
            <div class="dokme" style="display:inline-block; margin-right:10px;">
                <a href="<?php echo admin_url('admin.php?page=readysms-sms-settings'); ?>">تنظیمات پیامک</a>
            </div>
            <div class="dokme" style="display:inline-block;">
                <a href="<?php echo admin_url('admin.php?page=readysms-google-settings'); ?>">تنظیمات گوگل</a>
            </div>
        </div>

        <!-- راهنمای استفاده از پلاگین -->
        <h3>راهنمای استفاده از پلاگین</h3>
        <div class="instruction-box" style="background:#f7f9fc; padding:15px; border:1px solid #d1e3f0; border-radius:8px; margin-bottom:20px;">
            <p>این پلاگین به شما امکان می‌دهد تا ورود کاربران به وب‌سایت خود را از طریق پیامک و همچنین ورود با گوگل مدیریت کنید.</p>
            <ul style="line-height:1.8; margin:0; padding:0 20px;">
                <li>برای ورود با پیامک: شماره تلفن خود را وارد نماییده و کد تایید (OTP) را دریافت کنید.</li>
                <li>برای ورود با گوگل: تنظیمات مربوط به Google Client ID و Client Secret را در صفحه تنظیمات گوگل وارد نمایید.</li>
                <li>جهت دریافت راهنمای بیشتر، از مستندات و راهنمای ارائه شده در سایت استفاده کنید.</li>
            </ul>
        </div>

        <!-- راهنمای استفاده از گوگل -->
        <h3>راهنما استفاده از گوگل</h3>
        <div class="google-instruction" style="background:#fefbd8; padding:15px; border:1px solid #f5c76b; border-radius:8px; margin-bottom:20px;">
            توجه کنید: برای استفاده از لوگین گوگل باید حتما هاست خارج داشته باشید زیرا گوگل ایران را تحریم کرده است.
            <br><br>
            پس ممکن است به مشکل بخورید؛ ابتدا طبق راهنمای موجود در صفحه تنظیمات گوگل اقدام کنید. 
            در صورت بروز مشکل، جهت حذف دکمه ورود با گوگل می‌توانید از کد CSS زیر در قالب خود یا المانی که شورت کد را روی آن قرار داده‌اید، استفاده نمایید:
            <br>
            <code>.google-login-section { display:none!important; }</code>
        </div>

        <!-- خلاصه‌ای از طریقه‌ی استفاده از سامانه راه پیام -->
        <h3>خلاصه‌ای از طریقه‌ی استفاده از سامانه راه پیام</h3>
        <div class="instruction-box" style="background:#fcf8e3; padding:15px; border:1px solid #faebcc; border-radius:8px; margin-bottom:20px;">
            <p>سامانه راه پیام ابزاری قدرتمند برای ارسال پیامک‌های تاییدی و تبلیغاتی است. امکانات اصلی این سامانه شامل موارد زیر می‌باشد:</p>
            <ul style="line-height:1.8; margin:0; padding:0 20px;">
                <li>ارسال پیامک‌های تایید (OTP) به کاربران.</li>
                <li>بررسی و اعتبارسنجی کدهای دریافت شده از پیامک.</li>
                <li>ارسال پیامک آزمایشی برای تست صحت تنظیمات.</li>
                <li>مدیریت ارسال‌های پیامکی بر اساس محدودیت‌های زمانی و تعداد تلاش.</li>
                <li>دریافت اطلاعات وضعیت ارسال، قالب پیام و موجودی از طریق API.</li>
            </ul>
            <p>جهت استفاده بهینه از این سامانه، لطفاً تنظیمات مربوط به API (کلید API، شماره ارسال و کد پترن) را به درستی وارد نمایید.</p>
        </div>
        
        <!-- بخش تنظیمات افزونه -->
        <h3>تنظیمات افزونه</h3>
        <form method="post" action="options.php">
            <?php 
                settings_fields('readysms-sms-options'); 
                do_settings_sections('readysms-sms-options');
            ?>
            <table class="form-table">
                <tr>
                    <th>کلید API</th>
                    <td>
                        <input type="text" name="ready_sms_api_key" value="<?php echo esc_attr(get_option('ready_sms_api_key')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th>شماره ارسال (Provider)</th>
                    <td>
                        <input type="text" name="ready_sms_number" value="<?php echo esc_attr(get_option('ready_sms_number')); ?>" class="regular-text">
                        <p class="description">(مثلاً 3000 برای اپراتور مگفا، 2000 برای اپراتور آتیه، 9000 برای اپراتور آسیاتک، 50004 برای اپراتور ارمغان راه طلایی)</p>
                    </td>
                </tr>
                <tr>
                    <th>کد پترن (Template ID)</th>
                    <td>
                        <input type="text" name="ready_sms_pattern_code" value="<?php echo esc_attr(get_option('ready_sms_pattern_code')); ?>" class="regular-text">
                        <p class="description">کد پترنی که در سامانه راه پیام ثبت کرده‌اید.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('ذخیره تنظیمات'); ?>
        </form>
        
        <!-- بخش تست API -->
        <h3>بخش تست API سامانه راه پیام</h3>
        <!-- 1. تست ارسال پیامک (OTP) -->
        <div id="sms-test-section" style="margin-bottom:20px;">
            <h4>تست ارسال پیامک تاییدی (OTP)</h4>
            <p>شماره تلفن خود را وارد کنید:</p>
            <input type="text" id="test_phone_number" placeholder="مثال: 09123456789" class="regular-text">
            <button id="send_test_sms" class="button button-primary">ارسال پیامک آزمایشی</button>
            <div id="test_otp_section" style="display: none; margin-top: 10px;">
                <input type="text" id="test_otp_code" placeholder="کد تایید دریافتی" class="regular-text">
                <button id="verify_test_otp" class="button">بررسی کد</button>
            </div>
            <p id="test_result" style="margin-top: 10px;"></p>
        </div>
        
        <!-- 2. تست دریافت وضعیت پیام (API GET) -->
        <div id="status-test-section" style="margin-bottom:20px;">
            <h4>تست دریافت وضعیت ارسال پیامک</h4>
            <p>شناسه مرجع (OTPReferenceID) را وارد کنید:</p>
            <input type="text" id="status_reference_id" placeholder="مثال: XXXXXXXXXXXXXXXX" class="regular-text">
            <button id="check_status" class="button">بررسی وضعیت</button>
            <p id="status_result" style="margin-top: 10px;"></p>
        </div>
        
        <!-- 3. تست دریافت قالب پیام -->
        <div id="template-test-section" style="margin-bottom:20px;">
            <h4>تست دریافت قالب پیام</h4>
            <p>شناسه قالب (Template ID) را وارد کنید:</p>
            <input type="text" id="template_id" placeholder="مثال: 282" class="regular-text">
            <button id="get_template" class="button">دریافت قالب</button>
            <p id="template_result" style="margin-top: 10px;"></p>
        </div>
        
        <!-- 4. تست دریافت موجودی -->
        <div id="balance-test-section" style="margin-bottom:20px;">
            <h4>تست دریافت موجودی</h4>
            <button id="get_balance" class="button">دریافت موجودی</button>
            <p id="balance_result" style="margin-top: 10px;"></p>
        </div>
    </div>
    <?php
}

/**
 * صفحه تنظیمات ورود با گوگل
 */
function ready_login_render_google_settings() {
    ?>
    <div class="wrap">
        <h1>تنظیمات ورود با گوگل</h1>
        <form method="post" action="options.php">
            <?php 
                settings_fields('ready-google-options'); 
                do_settings_sections('ready-google-options');
            ?>
            <table class="form-table">
                <tr>
                    <th>Google Client ID</th>
                    <td>
                        <input type="text" name="ready_google_client_id" value="<?php echo esc_attr(get_option('ready_google_client_id')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th>Google Client Secret</th>
                    <td>
                        <input type="text" name="ready_google_client_secret" value="<?php echo esc_attr(get_option('ready_google_client_secret')); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
            <p>
                درصورتی که بلد نیستید چطور این پارامترها را پیدا کنید، 
                <a href="https://readystudio.ir/register-with-google-account/" target="_blank">اینجا کلیک کنید</a>.
            </p>
            <?php submit_button('ذخیره تنظیمات'); ?>
        </form>
    </div>
    <?php
}

/**
 * صفحه تنظیمات ورود با پیامک (Msgway) در پیشخوان وردپرس
 */
function ready_login_render_sms_settings() {
    ?>
    <div class="wrap">
        <!-- بنر افزونه با لینک وبلاگ -->
        <div style="text-align:center; margin-bottom:20px;">
            <a href="https://blog.msgway.com/contact/" target="_blank">
                <img src="<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/banner.jpg'; ?>" style="max-width:100%; height:auto; border-radius:12px;" alt="بنر ردی اس‌ام‌اس">
            </a>
        </div>
        <h1>تنظیمات ورود با پیامک (Msgway)</h1>
        <form method="post" action="options.php">
            <?php 
                settings_fields('readysms-sms-options'); 
                do_settings_sections('readysms-sms-options');
            ?>
            <table class="form-table">
                <tr>
                    <th>سامانه پیامکی</th>
                    <td>راه‌پیام</td>
                </tr>
                <tr>
                    <th>کلید API</th>
                    <td>
                        <input type="text" name="ready_sms_api_key" value="<?php echo esc_attr(get_option('ready_sms_api_key')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th>شماره ارسال (Provider)</th>
                    <td>
                        <input type="text" name="ready_sms_number" value="<?php echo esc_attr(get_option('ready_sms_number')); ?>" class="regular-text">
                        <p class="description">(مثلاً 3000، 2000، 9000 یا 50004)</p>
                    </td>
                </tr>
                <tr>
                    <th>کد پترن (Template ID)</th>
                    <td>
                        <input type="text" name="ready_sms_pattern_code" value="<?php echo esc_attr(get_option('ready_sms_pattern_code')); ?>" class="regular-text">
                        <p class="description">کد پترنی که در سامانه راه پیام ثبت کرده‌اید.</p>
                    </td>
                </tr>
            </table>
            <p style="margin-top:15px;">
                در صورتی که بلد نیستید چطور با این افزونه کار کنید،
                <a href="https://readystudio.ir/readysms-plugin/" target="_blank">این آموزش را بخوانید</a>.
            </p>
            <?php submit_button(); ?>
        </form>
        
        <!-- بخش تست ارسال پیامک (همانند فرم موجود در داشبورد) -->
        <h3>تست ارسال پیامک</h3>
        <div id="sms-test-section">
            <p>برای اطمینان از صحت تنظیمات، می‌توانید یک پیامک آزمایشی ارسال کنید.</p>
            <input type="text" id="test_phone_number" placeholder="شماره تلفن (مثال: 09123456789)" class="regular-text">
            <button id="send_test_sms" class="button button-primary">ارسال پیامک آزمایشی</button>
            <div id="test_otp_section" style="display: none; margin-top: 10px;">
                <input type="text" id="test_otp_code" placeholder="کد تایید دریافتی" class="regular-text">
                <button id="verify_test_otp" class="button">بررسی کد</button>
            </div>
            <p id="test_result" style="margin-top: 10px;"></p>
        </div>
        
        <h3>توجه</h3>
        <p>این افزونه از سامانه راه‌پیام برای ارسال پیامک استفاده می‌کند.</p>
        <div class="dokme">
            <a href="https://msgway.com/" target="_blank">سایت راه‌پیام</a>
        </div>
    </div>
    <?php
}

/**
 * افزودن متن "Powered by ReadyStudio" به صورت مینیمال در پایین تمامی صفحات پیشخوان
 */
function ready_login_render_power_by() {
    echo '<div class="power-by-readystudio" style="position: fixed; bottom: 5px; right: 5px; font-size: 12px; color: #888; z-index: 9999;">
            <a href="https://readystudio.ir/" target="_blank" style="color: #888; text-decoration: none;">Powered by ReadyStudio</a>
          </div>';
}
add_action('admin_footer', 'ready_login_render_power_by');
?>