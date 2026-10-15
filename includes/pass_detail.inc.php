<?php
session_start();

if (isset($_POST['pass_but']) && isset($_SESSION['userId'])) {

    require '../helpers/init_conn_db.php';  

    $mobile_flag = false;
    $flight_id   = $_POST['flight_id'];
    $passengers  = $_POST['passengers'];

    /* ---------------- MOBILE VALIDATION ---------------- */
    $mob_len = count($_POST['mobile']);
    for ($i = 0; $i < $mob_len; $i++) {
        if (strlen($_POST['mobile'][$i]) !== 10) {
            $mobile_flag = true;
            break;            
        }
    }

    if ($mobile_flag) {
        header('Location: ../pass_form.php?error=moblen');
        exit();         
    }

    /* ---------------- DOB VALIDATION (FIXED) ---------------- */
    $date_len = count($_POST['date']);
    for ($i = 0; $i < $date_len; $i++) {

        $dob = DateTime::createFromFormat('Y-m-d', $_POST['date'][$i]);
        $today = new DateTime();

        if (!$dob || $dob >= $today) {
            header('Location: ../pass_form.php?error=invdate');
            exit();
        }
    }

    /* ---------------- CHECK EXISTING PASSENGER ---------------- */
    $stmt = mysqli_stmt_init($conn);
    $sql = 'SELECT * FROM Passenger_profile WHERE flight_id = ? AND user_id = ?';

    if (!mysqli_stmt_prepare($stmt, $sql)) {
        header('Location: ../pass_form.php?error=sqlerror');
        exit();            
    } else {
        mysqli_stmt_bind_param($stmt, 'ii', $flight_id, $_SESSION['userId']);            
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $pass_id = null;

        while ($row = mysqli_fetch_assoc($result)) {
            $pass_id = $row['passenger_id'];
        }
    } 

    /* ---------------- RESET AUTO_INCREMENT ---------------- */
    if (is_null($pass_id)) {
        $stmt = mysqli_stmt_init($conn);
        $sql = 'ALTER TABLE Passenger_profile AUTO_INCREMENT = 1';

        if (!mysqli_stmt_prepare($stmt, $sql)) {
            header('Location: ../pass_form.php?error=sqlerror');
            exit();            
        } else {         
            mysqli_stmt_execute($stmt);
        }        
    }

    /* ---------------- INSERT PASSENGERS ---------------- */
    $stmt = mysqli_stmt_init($conn);
    $flag = false;

    for ($i = 0; $i < $date_len; $i++) {

        $sql = 'INSERT INTO Passenger_profile 
                (user_id, mobile, dob, f_name, m_name, l_name, flight_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)';

        if (!mysqli_stmt_prepare($stmt, $sql)) {
            header('Location: ../pass_form.php?error=sqlerror');
            exit();            
        } else {
            mysqli_stmt_bind_param(
                $stmt,
                'iissssi',
                $_SESSION['userId'],
                $_POST['mobile'][$i],
                $_POST['date'][$i],
                $_POST['firstname'][$i],
                $_POST['midname'][$i],
                $_POST['lastname'][$i],
                $flight_id
            );                           
            mysqli_stmt_execute($stmt);  
            $flag = true;        
        }
    }   

    /* ---------------- SUCCESS ---------------- */
    if ($flag) {
        $_SESSION['flight_id']  = $flight_id;
        $_SESSION['class']      = $_POST['class'];
        $_SESSION['passengers'] = $passengers;
        $_SESSION['price']      = $_POST['price'];
        $_SESSION['type']       = $_POST['type'];
        $_SESSION['ret_date']   = $_POST['ret_date'];
        $_SESSION['pass_id']    = $pass_id + 1;

        header('Location: ../payment.php');
        exit();          
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);    

} else {
    header('Location: ../pass_form.php');
    exit();  
}
?>
