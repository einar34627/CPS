
try:
    with open(r'c:\xampp\htdocs\CPS\admin\admin_dashboard.php', 'r', encoding='utf-8') as f:
        lines = f.readlines()

    # Delete lines 5374 to 5864 (inclusive).
    # 1-based 5374 is 0-based 5373.
    # 1-based 5864 is 0-based 5863.
    # We want to delete up to 5863 inclusive, so slice up to 5864.
    del lines[5373:5864]

    with open(r'c:\xampp\htdocs\CPS\admin\admin_dashboard.php', 'w', encoding='utf-8') as f:
        f.writelines(lines)
    print("Successfully deleted lines.")
except Exception as e:
    print(f"Error: {e}")
