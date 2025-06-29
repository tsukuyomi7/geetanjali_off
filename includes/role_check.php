<?php
// This file contains a function to check user roles for access control

function checkRole($requiredRole) {
    if (!isset($_SESSION['user_role'])) {
        return false; // Not logged in
    }

    $userRole = $_SESSION['user_role'];

    // Define role hierarchy (higher roles can access lower role pages)
    $roleHierarchy = [
        'student' => 1,
        'blog_editor' => 2,
        'event_manager' => 2,
        'moderator' => 3,
        'admin' => 4,
        'super_admin' => 5
    ];

    if (!isset($roleHierarchy[$userRole]) || !isset($roleHierarchy[$requiredRole])) {
        return false; // Invalid role defined somewhere
    }

    // A user can access a page if their role's level is greater than or equal to the required role's level
    return $roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole];
}
?>