<?php 
$c = file_get_contents('app/Http/Controllers/GL/DashboardController.php'); 
$c = preg_replace('/-, \$agent-, "->where('agent_id', \$agent->agent_id)", $c); 
file_put_contents('app/Http/Controllers/GL/DashboardController.php', $c); 
echo 'done'; 
