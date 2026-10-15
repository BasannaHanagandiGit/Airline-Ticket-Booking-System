<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;

if (isset($_POST['pay_but']) && isset($_SESSION['userId'])) {
    require '../helpers/init_conn_db.php';  
    $flight_id = $_SESSION['flight_id'];
    $price = $_SESSION['price'];
    $passengers = $_SESSION['passengers'];
    $pass_id = $_SESSION['pass_id'];
    $type = $_SESSION['type'];
    $class = $_SESSION['class'];
    $ret_date = $_SESSION['ret_date'];
    $card_no = $_POST['cc-number'];
    $expiry = $_POST['cc-exp'];  

    // Insert payment details
    $sql = 'INSERT INTO PAYMENT (user_id, expire_date, amount, flight_id, card_no) VALUES (?, ?, ?, ?, ?)';            
    $stmt = mysqli_stmt_init($conn);

    if (!mysqli_stmt_prepare($stmt, $sql)) {
        header('Location: ../payment.php?error=sqlerror');
        exit();            
    } else {
        mysqli_stmt_bind_param($stmt, 'isiis', $_SESSION['userId'], $expiry, $price, $flight_id, $card_no);          
        mysqli_stmt_execute($stmt);       
    }

    // Process each passenger
    $flag = false;
    for ($i = $pass_id; $i < $passengers + $pass_id; $i++) {
        $sql = 'SELECT * FROM Flight WHERE flight_id=?';
        if (!mysqli_stmt_prepare($stmt, $sql)) {
            header('Location: ../payment.php?error=sqlerror');
            exit();            
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $flight_id);            
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($result)) {
                $source = $row['source'];
                $dest = $row['Destination'];

                // Determine new seat assignment based on class
                $new_seat = assignSeat($row, $class);

                // Update seat counts and assign the seat
                updateSeats($conn, $flight_id, $new_seat, $class);

                // Insert into Ticket
                $sql = 'INSERT INTO Ticket (passenger_id, flight_id, seat_no, cost, class, user_id) VALUES (?, ?, ?, ?, ?, ?)';            
                if (!mysqli_stmt_prepare($stmt, $sql)) {
                    header('Location: ../payment.php?error=sqlerror');
                    exit();            
                } else {
                    mysqli_stmt_bind_param($stmt, 'iisisi', $i, $flight_id, $new_seat, $price, $class, $_SESSION['userId']);            
                    if (!mysqli_stmt_execute($stmt)) {
                        echo "Error: " . mysqli_error($conn);
                        exit();
                    }
                    $flag = true;
                }                                                                       
            } else {
                header('Location: ../payment.php?error=sqlerror');
                exit();                     
            }
        }
    }

    // Handle round trip if applicable
    if ($type === 'round' && $flag === true) {
        handleRoundTrip($conn, $dest, $source, $ret_date, $passengers, $pass_id, $class, $price, $_SESSION['userId']);
    }

    if ($flag) {
        unset($_SESSION['flight_id'], $_SESSION['passengers'], $_SESSION['pass_id'], $_SESSION['price'], $_SESSION['class'], $_SESSION['type'], $_SESSION['ret_date']);
        header('Location: ../my_flights.php'); // Redirect to my_flights
        exit();    
    } else {
        header('Location: ../payment.php?error=sqlerror');
        exit();               
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);        
} else {
    header('Location: ../payment.php');
    exit();  
}

function assignSeat($row, $class) {
    if ($class === 'B') {
        return calculateNewSeat($row['last_bus_seat']);
    } else if ($class === 'E') {
        return calculateNewSeat($row['last_seat'], 'E');
    }
}

function calculateNewSeat($last_seat, $classType = 'B') {
    if ($last_seat === '') {
        return $classType === 'B' ? '1A' : '21A';
    } else {
        $seat_num = (int)substr($last_seat, 0, -1);
        $seat_alpha = $last_seat[-1];

        if ($seat_alpha === 'F') {
            $seat_num++;
            $seat_alpha = 'A';
        } else {
            $seat_alpha = chr(ord($seat_alpha) + 1);
        }
        return $seat_num . $seat_alpha;
    }
}

function updateSeats($conn, $flight_id, $new_seat, $class) {
    $stmt = mysqli_stmt_init($conn);
    $column = $class === 'B' ? 'bus_seats' : 'Seats';
    $last_seat_column = $class === 'B' ? 'last_bus_seat' : 'last_seat';

    // Get current seat count
    $sql = "SELECT $column FROM Flight WHERE flight_id=?";
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        header('Location: ../payment.php?error=sqlerror');
        exit();
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $flight_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $seats = $row[$column] + 1; // Increment seat count
    }

    $sql = "UPDATE Flight SET $last_seat_column=?, $column=? WHERE flight_id=?";
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        header('Location: ../payment.php?error=sqlerror');
        exit();
    } else {
        mysqli_stmt_bind_param($stmt, 'sii', $new_seat, $seats, $flight_id);
        mysqli_stmt_execute($stmt);
    }
}

function handleRoundTrip($conn, $dest, $source, $ret_date, $passengers, $pass_id, $class, $price, $userId) {
    // Similar logic to check for return flight and assign seats
    // ...
}
