import urllib.request, json

# POST checkout
body = json.dumps({
    "customer_name": "Pelanggan QRISify Live",
    "customer_phone": "081234567899",
    "service_type": "dine_in",
    "table_number": "Meja 12",
    "payment_method": "qris",
    "notes": "Testing QRIS-ify gateway integration",
    "items": [{"product_id": 1, "quantity": 1}]
}).encode()

req = urllib.request.Request(
    "http://localhost:8088/api/orders/checkout",
    data=body,
    headers={"Content-Type": "application/json"},
    method="POST"
)

res = json.loads(urllib.request.urlopen(req).read().decode())
print("Success:", res["success"])
print("Invoice:", res["data"]["invoice_number"])
print("Total Amount:", res["data"]["total_amount"])
print("QRIS Image:", res["data"]["qris_image_url"][:80])
print("Receipt URL:", res["data"]["receipt_url"])

# Check database for qris_transaction_id
import sqlite3
import subprocess
out = subprocess.check_output([
    "php", "-r",
    "require 'config/database.php'; $pdo = App\Config\Database::connect(); "
    + "$s = $pdo->query('SELECT invoice_number, total_amount, qris_amount_total, qris_transaction_id, payment_status FROM sales ORDER BY id DESC LIMIT 1')->fetch(); "
    + "echo json_encode($s);"
]).decode()
print("DB Record:", out)
