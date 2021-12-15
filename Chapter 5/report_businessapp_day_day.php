<?php

$db_host="localhost";
$db_user="root";
$db_pass="dba";
$db_name="azure_costs";

// the name of the temporary database table for costs
$db_csvcost_victim_table="az_billing_victim";
// the name of the final database table for costs
$db_csvcost_table="az_billing_details";
// the name of the final table with the splitted costs by BusinessApplication
$db_splittedcosts_table="az_billing_splitted";


// we need to compare yesterday and the day before yesterday.
$today=date("Y-m-d");
$yesterday=date('Y-m-d',strtotime("-1 days"));
$tdbefore=date('Y-m-d',strtotime("-2 days"));

// associative array to store costs for business applications
$output_array=[];

// connection to the database
$link = mysqli_connect( $db_host, $db_user, $db_pass, $db_name);
if (!$link) {
  $err=mysqli_connect_errno();
  echo "ERROR : unable to connect to db server or db ".$db_name." (".$err.")\r\n";
  return 1;
}



// get splitted costs for the day before yesterday and store them in the associative array.
$sql="select BsnApp,truncate(sum(SplittedCost),2) as Cost from ".$db_splittedcosts_table." where Date='".$tdbefore."' group by BsnApp order by BsnApp; ";
$result = mysqli_query($link, $sql);
if (mysqli_num_rows($result) > 0) { 
  //for each row insert in the associative array the label as the key, and the costs as column[0]
  while($row = mysqli_fetch_assoc($result)) {	  
    $bsnapp=$row["BsnApp"];
    $cost=$row["Cost"];

    $output_array[$bsnapp]=[];
    $output_array[$bsnapp][0]=$cost;
    $output_array[$bsnapp][1]=0;
    $output_array[$bsnapp][2]=0;
    $output_array[$bsnapp][3]="-100";
    $output_array[$bsnapp][4]="\u{1F4C9}";
  }
}

$sql="select BsnApp,truncate(sum(SplittedCost),2) as Cost from ".$db_splittedcosts_table." where Date='".$yesterday."' group by BsnApp order by BsnApp; ";
$result = mysqli_query($link, $sql);
if (mysqli_num_rows($result) > 0) {
  //for each row insert in the associative array the label as the key, and the costs as column[1]
  while($row = mysqli_fetch_assoc($result)) {
    $bsnapp=$row["BsnApp"];
    $cost=$row["Cost"];

    if (!isset($output_array[$bsnapp]))
      $output_array[$bsnapp]=[];

    $output_array[$bsnapp][1]=$cost;

    //in case we have new BusinessApplication yesterday, compared to the day before
    //we need to 0-fill the missing previous values.
    if ( !isset($output_array[$bsnapp][0]) ){
      $output_array[$bsnapp][0]=0;
    }


    // let's calculate the DELTA in column[2] and PERCENTAGE in column[3]
    $delta = $output_array[$bsnapp][1] - $output_array[$bsnapp][0];
    $output_array[$bsnapp][2]=$delta;
    if ($output_array[$bsnapp][1]>0){
      // uncomment the following line for the percentage of yesterday versus the day before
      $output_array[$bsnapp][3]=round(($output_array[$bsnapp][0] * 100) / $output_array[$bsnapp][1],3);
      // uncomment the following line for the percentage
      $output_array[$bsnapp][3]=round(($delta * 100) / $output_array[$bsnapp][1],3);

    }else{
      if ($output_array[$bsnapp][1]>0 )
        $output_array[$bsnapp][3]="-100";
      else
        $output_array[$bsnapp][3]="0";
    }

    // let's insert the emoji icon trend
    if ( round($delta,3) > 0 ){
      $output_array[$bsnapp][4]="\u{1F4C8}";
    }else if ( round($delta,3) == 0 ) {
      $output_array[$bsnapp][4]="\u{003D}";
    }else{
      $output_array[$bsnapp][4]="\u{1F4C9}";
    }

  }
}

// print html output
echo "<html><head><title>BusinessApplication day/day report</title></head><body>\r\n";
echo "<table width='90%' cellspacing='0' cellpadding='0'>\r\n";
echo "<tr><th>BusinessApp</th><th>Day before yesterday<br /><small>".$tdbefore."</small></th><th>Yesterday<br /><small>".$yesterday."</small></th><th>Delta cost</th><th>Percentage</th><th>Trend</th></tr>\r\n";
foreach ($output_array as $key => $bsnapp) {
  echo "<tr><td>".$key."</td><td align='right'>".$bsnapp[0]."</td><td align='right'>".$bsnapp[1]."</td><td align='right'>".$bsnapp[2]."</td><td align='center'>".$bsnapp[3]."%</td><td align='center'>".$bsnapp[4]."</td></tr>\r\n";
}

echo "</table>";
echo "</body></html>";

mysqli_close($link);
return 0;
?>


