<?php
// 1. Setup Connections and Auth
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

$message = "";
$error = "";

// 2. Logic for Saving Driver (POST Method)
if (isset($_POST['add_driver'])) {
    $name     = mysqli_real_escape_string($conn, $_POST['name']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $contact  = mysqli_real_escape_string($conn, $_POST['contact']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = 'driver';
    $profile  = 'default.png'; // Awtomatikong default image para sa lahat ng bago

    // Check if email exists
    $checkEmail = mysqli_query($conn, "SELECT email FROM users WHERE email = '$email'");

    if (mysqli_num_rows($checkEmail) > 0) {
        $error = "This email is already in use.";
    } else {
        // Insert Query (Puro Text Data na lang)
        $sql = "INSERT INTO users (name, email, password, contact, profile, role) 
                VALUES ('$name', '$email', '$password', '$contact', '$profile', '$role')";

        if (mysqli_query($conn, $sql)) {
            $message = "Driver account successfully created!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Drivers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        @font-face {
            font-family: 'NaruMonoDemo';
            src: url('../assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
        }
        body, input, button, table, div, h3, p, label {
            font-family: 'NaruMonoDemo', sans-serif !important;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<?php include '../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <div class="lg:col-span-4 order-2 lg:order-1">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden sticky top-6 border border-gray-100">
                <div class="bg-indigo-600 px-6 py-4 text-white">
                    <h3 class="font-semibold flex items-center gap-2">
                        <i class="fas fa-user-plus"></i> Register Driver
                    </h3>
                </div>

                <div class="p-6">
                    <?php if($message): ?>
                        <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4 text-sm border border-green-200"><?= $message ?></div>
                    <?php endif; ?>
                    <?php if($error): ?>
                        <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm border border-red-200"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="text-xs font-bold text-gray-500 mb-1 block uppercase">Full Name</label>
                            <input type="text" name="name" class="w-full border rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" required>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-500 mb-1 block uppercase">Email Address</label>
                            <input type="email" name="email" class="w-full border rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" required>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-500 mb-1 block uppercase">Contact Number</label>
                            <input type="text" name="contact" class="w-full border rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" required>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-500 mb-1 block uppercase">Temporary Password</label>
                            <input type="password" name="password" class="w-full border rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" required>
                        </div>

                        <button type="submit" name="add_driver" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition mt-2">
                            SAVE DRIVER
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="lg:col-span-8 order-1 lg:order-2">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
                <div class="px-6 py-5 bg-gray-50 border-b flex justify-between items-center">
                    <h3 class="font-bold text-gray-800 italic tracking-wider">ACTIVE DRIVERS</h3>
                    <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full uppercase">Masterlist</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase border-b">
                            <tr>
                                <th class="px-6 py-4">Profile</th>
                                <th class="px-6 py-4">Driver Info</th>
                                <th class="px-6 py-4">Contact</th>
                                <th class="px-6 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM users WHERE role='driver' ORDER BY id DESC");
                            while($row = mysqli_fetch_assoc($res)):
                                // Ipapakita pa rin ang existing profile photo kung meron, default kung wala
                                $img_filename = $row['profile'] ?: 'default.png';
                                $img_path = "../uploads/drivers_profile/" . $img_filename;
                            ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-6 py-4">
                                    <img src="<?= $img_path ?>" class="w-10 h-10 rounded-full object-cover ring-2 ring-indigo-50" onerror="this.src='../uploads/drivers_profile/default.png'">
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($row['name']) ?></p>
                                    <p class="text-[11px] text-gray-400"><?= htmlspecialchars($row['email']) ?></p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($row['contact']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center gap-3">
                                        <a href="edit_driver.php?id=<?= $row['id'] ?>" class="text-indigo-600 hover:scale-110 transition"><i class="fas fa-edit"></i></a>
                                        <a href="delete_driver.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this driver?')" class="text-red-500 hover:scale-110 transition"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>