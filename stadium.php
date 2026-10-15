<?php
$gates = $gates ?? [];
$selectedGate = $selectedGate ?? '';
$mid = $mid ?? 0;

if (empty($gates)) {
    echo "<p>Δεν υπάρχουν διαθέσιμες θύρες.</p>";
    return;
}

if (!$selectedGate):
?>

<h2 style="text-align:center; margin-top:40px;">Επιλέξτε Θύρα</h2>

<div class="stadium-container" style="position:relative; max-width:800px; margin:30px auto;">
    <img src="https://i.imgur.com/lTaK20e.jpeg" 
         style="width:100%; display:block; border-radius:10px;">

    <?php
    // Θέσεις θυρών σε ποσοστά (responsive)
    $gatePositions = [
        '1'     => ['x'=>50, 'y'=>80],
        '2'     => ['x'=>68, 'y'=>79],
        '3'     => ['x'=>35, 'y'=>80],
        '4'     => ['x'=>17, 'y'=>49],
        'VIP'   => ['x'=>47, 'y'=>91],
        'VIP A' => ['x'=>56, 'y'=>91],
    ];

    foreach($gates as $gateNum => $seatsInGate){
        $pos = $gatePositions[$gateNum] ?? ['x'=>50,'y'=>50];
        echo "<a href='?page=seats&match=$mid&gate=".urlencode($gateNum)."' 
                 style='position:absolute; left:{$pos['x']}%; top:{$pos['y']}%;
                        transform:translate(-50%, -50%);
                        width:50px; height:50px; background:#000; color:#fff; 
                        border-radius:50%; display:flex; align-items:center; justify-content:center;
                        text-decoration:none; font-weight:bold; border: 2px solid #ffffffff; box-shadow:0 2px 6px rgba(0,0,0,0.5);'>
                 $gateNum
              </a>";
    }
    ?>
</div>

<p style="text-align:center; color:#555; margin-top:20px;">
    Κάντε κλικ πάνω σε μια θύρα για να δείτε τις διαθέσιμες θέσεις.
</p>

<?php
else:
    // --- Εμφάνιση θέσεων επιλεγμένης θύρας ---
    echo "<h2 style='text-align:center; margin-top:40px;'>Θύρα $selectedGate</h2>";
    echo "<form method='POST' action='?page=seats&match=$mid&gate=$selectedGate'>";
    echo "<div class='seat-container'>";

    $rows = [];
    foreach ($gates[$selectedGate] as $s) {
        $rows[$s['seat_row']][] = $s;
    }

    foreach ($rows as $row => $seats_in_row) {
        echo "<div class='row'>";
        foreach ($seats_in_row as $s) {
            $seatKey = $s['Gate'].'-'.$s['seat_row'].'-'.$s['seat_number'];
            $seatClass = $s['available'] ? 'seat booked' : 'seat available';
            $disabled = $s['available'] ? 'disabled' : '';
            echo "<label class='$seatClass'>
                    <input type='checkbox' name='selected_seats[]' value='$seatKey' $disabled>
                    Θ{$s['seat_number']}
                  </label>";
        }
        echo "</div>";
    }

    echo "</div>";
    echo "<button type='submit' name='add_to_cart' class='add-cart-btn'>Προσθήκη στο καλάθι</button>";
    echo "</form>";
endif;
?>
