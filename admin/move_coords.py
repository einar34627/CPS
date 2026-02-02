
import os

path = r"c:\xampp\htdocs\CPS\admin\admin_dashboard.php"
try:
    with open(path, 'r', encoding='utf-8') as f:
        lines = f.readlines()

    coords_line_index = -1
    coords_line_content = ""

    for i, line in enumerate(lines):
        if "const commonwealthCoords = [[" in line:
            coords_line_index = i
            coords_line_content = line
            break

    if coords_line_index != -1:
        # Find insertion point: async function initCommonwealthMap(){
        insert_index = -1
        for i, line in enumerate(lines):
            if "async function initCommonwealthMap(){" in line:
                insert_index = i
                break
                
        if insert_index != -1:
            # Prepare the moved line
            clean_content = coords_line_content.strip()
            # Ensure it ends with semicolon if it doesn't (it usually does)
            
            # Modify the lines
            # 1. Comment out the old line to avoid duplicate declaration if we keep const
            # But wait, if we move it out, we should remove the 'const' inside if we want to use the global one?
            # Or just let the inner function use the global one if we remove the inner declaration.
            # Yes, commenting out the inner declaration is correct.
            lines[coords_line_index] = "// Moved to global: " + lines[coords_line_index]
            
            # 2. Insert new line at global scope
            lines.insert(insert_index, clean_content + "\n")
            
            with open(path, 'w', encoding='utf-8') as f:
                f.writelines(lines)
            print("Successfully moved commonwealthCoords")
        else:
            print("Insertion point not found")
    else:
        print("Coordinates line not found")
except Exception as e:
    print(f"Error: {e}")
