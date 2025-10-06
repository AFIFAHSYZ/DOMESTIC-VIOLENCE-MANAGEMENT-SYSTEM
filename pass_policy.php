<?php
function validatePasswordPolicy($password) {
    // Minimum 8 characters
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long";
    }
    
    // At least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter";
    }
    
    // At least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter";
    }
    
    // At least one number
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number";
    }
    
    // At least one special character
    if (!preg_match('/[\W_]/', $password)) {
        return "Password must contain at least one special character";
    }
    
    return true;
}