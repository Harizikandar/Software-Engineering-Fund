<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('foodiesys_vendor');
    session_start();
}
