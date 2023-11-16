<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php include('inc/links.php'); ?>
  <title><?php echo $settings_r['site_title'] ?> - CONFIRM BOOKING</title>

</head>

<body class="bg-light">

  <?php require('inc/header.php'); ?>

  <?php

  /*
    check room id from url is present or not. shutdown mode is active or not. User is logged in or not.
  */
  if (!isset($_GET['id']) || $settings_r['shutdown'] == true) {
    redirect('rooms.php');
  } else if(!(isset($_SESSION['login']) && $_SESSION['login'] == true)) {
    redirect('rooms.php');
  }

  // filter and get room and user data
  $data = filteration($_GET);
  $room_res = select("SELECT * FROM `rooms` 
    WHERE `id`=? AND `status`=? AND `removed`=?", [$data['id'], 1, 0], 'iii');

  if (mysqli_num_rows($room_res) == 0) {
    redirect('rooms.php');
  }

  $room_data = mysqli_fetch_assoc($room_res);

  $_SESSION['room'] = [
    "id" => $room_data['id'],
    "name" => $room_data['name'],
    "price" => $room_data['price'],
    "payment" => null,
    "available" => false,
  ];

  $user_res = select("SELECT * FROM `user_cred` WHERE `id` = ? LIMIT 1",
  [$_SESSION['uId']], "i");
  $user_data = mysqli_fetch_assoc($user_res);
  $frm_data = filteration($_POST);

  if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_now'])) {
    processBooking($_POST);
  }

  function processBooking($frm_data) {
    alert('success', 'Successfully Booked!');
    $checkin_date = new DateTime($frm_data['checkin']);
    $checkout_date = new DateTime($frm_data['checkout']);
    $interval = $checkin_date->diff($checkout_date);
    $total_pay = calculateTotalPay($interval->days);

    if (sessionVariablesSet()) {
        insertBookingData($total_pay, $frm_data);
    } else {
        echo "Session variables not set.";
    }
  }

  function calculateTotalPay($number_of_nights) {
    return $_SESSION['room']['price'] * $number_of_nights;
  }

  function sessionVariablesSet() {
    return isset($_SESSION['room']['id'], $_SESSION['room']['name'], $_SESSION['room']['price']);
  }

  function insertBookingData($total_pay, $frm_data) {
    global $conn;
    $query1 = prepareBookingOrderQuery();
    $order_id = generateOrderId();
    $trans_id = generateTransactionID(10);
    $datentime = date('Y-m-d H:i:s');
    insert($query1, [$_SESSION['room']['id'], $_SESSION['uId'], $frm_data['checkin'], $frm_data['checkout'], 0, 0, 'booked', $order_id, $trans_id, $total_pay, 'Success', 'Payment processed successfully', $datentime], 'iissiisiiisss');
    $booking_id = mysqli_insert_id($conn);
    insertBookingDetails($booking_id, $total_pay, $frm_data);
  }

  function prepareBookingOrderQuery() {
    return "INSERT INTO `booking_order`(`room_id`, `user_id`, `check_in`, `check_out`, `arrival`, `refund`, `booking_status`, `order_id`, `trans_id`, `trans_amt`, `trans_status`, `trans_resp_msg`, `datentime`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
  }

  function insertBookingDetails($booking_id, $total_pay, $frm_data) {
      $query2 = "INSERT INTO `booking_details`(`booking_id`, `room_name`, `price`, `total_pay`, `room_no`, `username`, `phonenum`, `address`) VALUES (?,?,?,?,?,?,?,?)";
      insert($query2, [$booking_id, $_SESSION['room']['name'], $_SESSION['room']['price'], $total_pay, $_SESSION['room']['id'], $frm_data['name'], $frm_data['phonenum'], $frm_data['address']], 'isiiisss');
  }

  function generateOrderId() {
      return rand(1, 1000);
  }

  function generateTransactionID($length = 10) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
  }
  ?>

  <div class="container">
    <div class="row">

      <div class="col-12 my-5 mb-4 px-4">
        <h2 class="fw-bold">CONFIRM BOOKING</h2>
        <div style="font-size: 14px;">
          <a href="index.php" class="text-secondary text-decoration-none">HOME</a>
          <span class="text-secondary"> > </span>
          <a href="room.php" class="text-secondary text-decoration-none">ROOMS</a>
          <span class="text-secondary"> > </span>
          <a href="#" class="text-secondary text-decoration-none">CONFIRM</a>
        </div>
      </div>

      <div class="col-lg-7 col-md-12 px-4">
        <?php
          $room_thumb = ROOMS_IMG_PATH . "thumbnail.jpg";
          $thumb_q = mysqli_query($conn, "SELECT * FROM `room_images` 
              WHERE `room_id` = '$room_data[id]' 
              AND `thumb` = '1'");
          if (mysqli_num_rows($thumb_q) > 0) {
            $thumb_res = mysqli_fetch_assoc($thumb_q);
            $room_thumb = ROOMS_IMG_PATH . $thumb_res['image'];
          }

          echo <<< data
            <div class="card p-3 shadow-sm rouded">
              <img src="$room_thumb" class="img-fluid rounded mb-3">
              <h5>$room_data[name]</h5>
              <h6>$$room_data[price] per night</h6>
            </div>
          data;
        ?>
      </div>

      <div class="col-lg-5 col-md-12 px-4">
        <div class="card mb-4 border-0 shadow-sm rounded-3">
          <div class="card-body">
            <form action="" id="booking_form" method="POST">
              <h6 class="mb-3">BOOKING DETAILS</h6>
              <div class= "row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Name</label>
                  <input name="name" type="text" value="<?php echo $user_data['name']?>" class="form-control shadow-none" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Phone Number</label>
                  <input name="phonenum" type="number" value="<?php echo $user_data['phonenum']?>" class="form-control shadow-none" required>  
                </div>
                <div class="col-md-12 mb-3">
                  <label class="form-label">Address</label>
                  <input name="address" type="text" value="<?php echo $user_data['address']?>" class="form-control shadow-none" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Check-in</label>
                  <input name="checkin" onchange="check_availability()" type="date" class="form-control shadow-none" required>  
                </div>
                <div class="col-md-6 mb-4">
                  <label class="form-label">Check-out</label>
                  <input name="checkout" onchange="check_availability()" type="date" class="form-control shadow-none" required>  
                </div>
                
                <div class="col-12">
                  <div class="spinner-border text-info mb-3 d-none" id="info_loader" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                  
                  <h6 class="mb-3 text-danger" id="pay_info">Provide check-in & check-out date!</h6>
                  <button name="pay_now" class="btn w-100 text-white custom-bg shadow-none mb-1"  >Pay now</button>
                </div>
              </div>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div>

  <?php require('inc/footer.php'); ?>
  <script>
    let booking_form = document.getElementById('booking_form');
    let info_loader = document.getElementById('info_loader');
    let pay_info = document.getElementById('pay_info');
    
    function check_availability() {
      let checkin_val = booking_form.elements['checkin'].value;
      let checkout_val = booking_form.elements['checkout'].value; 
      booking_form.elements['pay_now'].setAttribute('disabled', true);

      if (checkin_val!= '' && checkout_val != '') {
        //d-none bi an di
        pay_info.classList.add('d-none');
        pay_info.classList.replace('text-dark', 'text-danger');

        // quay spinner được giải phóng
        info_loader.classList.remove('d-none');

        let data = new FormData();
        
        data.append('check_availability','');
        data.append('check_in',checkin_val);
        data.append('check_out',checkout_val);

        let xhr = new XMLHttpRequest();
        xhr.open("POST", "ajax/confirm_booking.php", true);

        xhr.onload = function() {
          let data = JSON.parse(this.responseText);
          if (data.status == 'check-in-out-equal') {
            pay_info.innerText = "You cannot check-out date on the same day!";
          } else if (data.status == 'check_out_earlier') {
            pay_info.innerText = "You cannot check-out date is earlier than check-in date!";
          } else if (data.status == 'check_in_earlier') {
            pay_info.innerText = "You cannot check-in date is earlier than today's date!";
          } else if (data.status == 'unvailabel') {
            pay_info.innerText = "Room not availabel for this check-in date!";
          } else {
            pay_info.innerHTML = "No. of Days: "+ data.days + "<br>Total Amount to Pay: $" + data.payment;
            pay_info.classList.replace('text-danger', 'text-dark');
            booking_form.elements['pay_now'].removeAttribute('disabled');
          }
          pay_info.classList.remove('d-none');
          info_loader.classList.add('d-none');
        }
        xhr.send(data);
      }
    }
  </script>    
</body>
</html>