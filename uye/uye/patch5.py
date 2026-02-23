import sys

file_path = r"C:\Users\monster\Desktop\tpjd\uye\uye\api\send_email.php"
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

bad_use = """use PHPMailer\\PHPMailer\\PHPMailer;
use PHPMailer\\PHPMailer\\Exception;
use PHPMailer\\PHPMailer\\SMTP;"""

if bad_use in content:
    content = content.replace(bad_use, "")
    content = content.replace("<?php", "<?php\n\n" + bad_use)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Fixed send_email.php use statements!")
