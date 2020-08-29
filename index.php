<?php
include('scripts/db.php');

$logs = run_query('SELECT * FROM logs ORDER BY created_at DESC LIMIT 100');

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Webflow + RETS</title>

    <!-- Favicons -->
    <meta name="theme-color" content="#563d7c">

    <!-- Bootstrap core CSS -->
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/dashboard.css">


    <style>
        .bd-placeholder-img {
            font-size: 1.125rem;
            text-anchor: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        @media (min-width: 768px) {
            .bd-placeholder-img-lg {
                font-size: 3.5rem;
            }
        }
    </style>
    <!-- Custom styles for this template -->
    <style type="text/css">

    </style>
</head>

<body>
    <nav class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 mr-0 px-3" href="#">Webflow + RETS</a>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="sidebar-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="https://getbootstrap.com/docs/4.5/examples/dashboard/#">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-home">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                </svg>
                                Logs
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">

                <h2 class="my-4">Logs History</h2>

                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>RETS Listings</th>
                                <th>Created</th>
                                <th>Updated</th>
                                <th>Deleted</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($logs as $log) {
                            ?>
                                <tr>
                                    <td><?= $log['created_at'] ?></td>
                                    <td><?= $log['rets_count'] ?></td>
                                    <td><?= $log['added'] ?></td>
                                    <td><?= $log['updated'] ?></td>
                                    <td><?= $log['deleted'] ?></td>
                                    <td><?= $log['errors'] ?></td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/jquery-3.5.1.min.js"></script>
    <script src="assets/bootstrap.min.js"></script>
</body>

</html>