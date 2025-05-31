<?php
header("Content-Type: text/css; charset=UTF-8");
?>

/* ------------------------------
   General Styles for Plugin (Admin & Front)
------------------------------ */

body {
  font-family: 'Yekan', 'Helvetica Neue', Helvetica, Arial, sans-serif;
  color: #333;
  margin: 0;
  padding: 0;
  background-color: #f4f6f9;
}

/* ------------------------------
   Admin Dashboard Styling
------------------------------ */

.wrap {
  background: #fff;
  padding: 25px 30px;
  margin: 20px auto;
  max-width: 1000px;
  border: 1px solid #eaeaea;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  border-radius: 10px;
}

.wrap h1, .wrap h3 {
  font-weight: 600;
  margin-bottom: 20px;
  color: #333;
}

.dokme-container {
  display: flex;
  gap: 20px;
  margin-bottom: 20px;
}

.dokme a {
  background: #3498db;
  color: #fff;
  padding: 10px 18px;
  border-radius: 5px;
  text-decoration: none;
  transition: background 0.3s ease;
}

.dokme a:hover {
  background: #2980b9;
}

/* Google Instruction Styling */
.google-instruction {
  background: #fefbd8;
  border: 1px solid #f5c76b;
  border-radius: 6px;
  padding: 15px;
  color: #665200;
  font-size: 14px;
  margin-bottom: 20px;
  line-height: 1.6;
}

/* Instruction Boxes */
.instruction-box {
  background: #f7f9fc;
  border: 1px solid #d1e3f0;
  border-radius: 8px;
  padding: 15px;
  margin-bottom: 20px;
  font-size: 14px;
}

/* ------------------------------
   Front-End SMS Login Form Styling
------------------------------ */

#sms-login-form,
#verify-otp-form {
  max-width: 420px;
  margin: 40px auto;
  padding: 25px;
  background: #ffffff;
  border: 1px solid #ddd;
  border-radius: 10px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

#sms-login-form label,
#verify-otp-form label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #444;
}

#sms-login-form input[type="text"],
#verify-otp-form input[type="text"] {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ccc;
  border-radius: 6px;
  margin-bottom: 15px;
  font-size: 14px;
  box-sizing: border-box;
}

#sms-login-form button,
#verify-otp-form button {
  background: #3498db;
  color: #fff;
  border: none;
  padding: 10px 18px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 15px;
  transition: background 0.3s ease;
}

#sms-login-form button:hover,
#verify-otp-form button:hover {
  background: #2980b9;
}

/* Timer Styling */
#timer-display {
  display: none;
  font-size: 14px;
  color: #ff0000;
}

/* ------------------------------
   Toastr Notification Overrides
------------------------------ */

#toast-container > .toast {
  border-radius: 6px;
  font-size: 14px;
  padding: 10px 15px;
}

/* ------------------------------
   Power By ReadyStudio Styling
------------------------------ */

.power-by-readystudio {
  position: fixed;
  bottom: 5px;
  right: 5px;
  font-size: 12px;
  color: #888;
  z-index: 9999;
}

.power-by-readystudio a {
  color: #888;
  text-decoration: none;
  transition: color 0.3s ease;
}

.power-by-readystudio a:hover {
  color: #555;
}

/* ------------------------------
   Responsive Adjustments
------------------------------ */

@media (max-width: 600px) {
  .wrap {
    margin: 10px;
    padding: 15px;
  }
  
  #sms-login-form,
  #verify-otp-form {
    margin: 20px;
    padding: 20px;
  }
}