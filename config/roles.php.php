<?php
function requireRole($role){
 if($_SESSION['role']!=$role){
   die("Access denied");
 }
}
?>