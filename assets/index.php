<?php
// Redirect any requests to assets/index.php back to the bursary root login.
header('Location: ../index.php', true, 302);
exit;
?>

        else
            {
            #echo "<script>alert('Access Denied Please Check Your Credentials');</script>";
                $err = "Access Denied Please Check Your Credentials";
            }


       
    }


function pharmacyopeningstock($date,$mysqli){
    $sql="SELECT * FROM pharmacy_stock where date='$date'"; 
   $result = mysqli_query($mysqli,$sql);
    $num=mysqli_num_rows($result);
        if($num>0){


        }
        else{
            $sqll="SELECT * FROM pharmacy order by id ASC"; 
            $results = mysqli_query($mysqli,$sqll);
            while($reply = mysqli_fetch_array($results)){
                $name=$reply['name'];
                $qnt=$reply['quantity'];
                $quey="insert into pharmacy_stock(name,opening,addstock,closing,date) values('$name','$qnt','0','$qnt','$date')";
                $st2 = mysqli_query($mysqli,$quey);
                           
                        }
            }

    }

function storeopeningstock($date,$mysqli){
    $sql="SELECT * FROM store_stock where date='$date'"; 
   $result = mysqli_query($mysqli,$sql);
    $num=mysqli_num_rows($result);
    if($num>0){


    }
    else{
        $sqll="SELECT * FROM drug order by id ASC"; 
        $results = mysqli_query($mysqli,$sqll);
        while($reply = mysqli_fetch_array($results)){
                $name=$reply['name'];
                $qnt=$reply['quantity'];
                $quey="insert into store_stock(name,opening,addstock,closing,date) values('$name','$qnt','0','$qnt','$date')";
                $st2 = mysqli_query($mysqli,$quey);
                           
                }
            }

    
}
?>
<!--End Login-->php tools/create_admin.php admin Admin@1234 "Administrator" admin
<!DOCTYPE html>
<html lang="en">
    
<head>
        <meta charset="utf-8" />
        <title> OOU Bursary | Inventory Management System</title></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta content="" name="description" />
        <meta content="" name="MartDevelopers" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <!-- App favicon -->
        <link rel="shortcut icon" href="assets/images/oou.png">

        <!-- App css -->
        <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
        <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
        <!--Load Sweet Alert Javascript-->
        
        <script src="assets/js/swal.js"></script>
        <!--Inject SWAL-->
        <?php if(!empty($success)) {?>
        <!--This code for injecting an alert-->
                <script>
                            setTimeout(function () 
                            { 
                                swal("Success", <?php echo json_encode($success); ?>, "success");
                            },
                                100);
                </script>

        <?php } ?>

        <?php if(!empty($err)) {?>
        <!--This code for injecting an alert-->
                <script>
                            setTimeout(function () 
                            { 
                                swal("Failed", <?php echo json_encode($err); ?>, "error");
                            },
                                100);
                </script>

        <?php } ?>



    </head>

    <body class="authentication-bg authentication-bg-pattern" >

        <div class="account-pages mt-5 mb-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6 col-xl-5">
                        <div class="card bg-pattern">

                            <div class="card-body p-4" >
                                
                                <div class="text-center w-75 m-auto">
                                    <a href="index.php">
                                        <span><img src="assets/images/OOU.png" alt="" height="46"></span>
                                        <span><img src="assets/images/logo-dark.png" alt="" height="22"></span>
                                    </a>
                                    <p class="text-muted mb-4 mt-3">Enter your username and password to access your portal.</p>
                                </div>

                                <form method='post' >

                                    <div class="form-group mb-3">
                                        <label for="emailaddress">Staff Number</label>
                                        <input class="form-control" name="ad_id" type="text" id="emailaddress" required="" placeholder="Enter your number">
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="password">Password</label>
                                        <input class="form-control" name="ad_pwd" type="password" required="" id="password" placeholder="Enter your password">
                                    </div>

                                    <div class="form-group mb-0 text-center">
                                        <button name="admin_login" type="submit" class="ladda-button btn btn-primary"  data-style="expand-right"> Clinic Login Only </button>
                                    </div>

                                </form>

                                <!--
                                For Now Lets Disable This 
                                This feature will be implemented on later versions
                                <div class="text-center">
                                    <h5 class="mt-3 text-muted">Sign in with</h5>
                                    <ul class="social-list list-inline mt-3 mb-0">
                                        <li class="list-inline-item">
                                            <a href="javascript: void(0);" class="social-list-item border-primary text-primary"><i class="mdi mdi-facebook"></i></a>
                                        </li>
                                        <li class="list-inline-item">
                                            <a href="javascript: void(0);" class="social-list-item border-danger text-danger"><i class="mdi mdi-google"></i></a>
                                        </li>
                                        <li class="list-inline-item">
                                            <a href="javascript: void(0);" class="social-list-item border-info text-info"><i class="mdi mdi-twitter"></i></a>
                                        </li>
                                        <li class="list-inline-item">
                                            <a href="javascript: void(0);" class="social-list-item border-secondary text-secondary"><i class="mdi mdi-github-circle"></i></a>
                                        </li>
                                    </ul>
                                </div> 
                                -->

                            </div> <!-- end card-body -->
                        </div>
                        <!-- end card -->

                        <div class="row mt-3">
                            <div class="col-12 text-center">
                                <p> <a href="his_admin_pwd_reset.php" class="text-white-50 ml-1">Forgot your password?</a></p>
                               <!-- <p class="text-white-50">Don't have an account? <a href="his_admin_register.php" class="text-white ml-1"><b>Sign Up</b></a></p>-->
                            </div> <!-- end col -->
                        </div>
                        <!-- end row -->

                    </div> <!-- end col -->
                </div>
                <!-- end row -->
            </div>
            <!-- end container -->
        </div>
        <!-- end page -->


        <?php include ("assets/inc/footer1.php");?>

        <!-- Vendor js -->
        <script src="assets/js/vendor.min.js"></script>

        <!-- App js -->
        <script src="assets/js/app.min.js"></script>
        
    </body>

</html>