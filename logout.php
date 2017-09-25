<?php
   session_start();
   
   if(session_destroy()) {
      header("Location: public/login.php?loggedOut");
   }
?>