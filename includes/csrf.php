<input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

if (!validate_csrf_token($_POST['csrf_token'])) {
    die("Invalid CSRF token");
}