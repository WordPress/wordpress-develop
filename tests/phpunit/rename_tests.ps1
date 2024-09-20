
$directory = "tests\hooks"


$files = Get-ChildItem -Path $directory -Filter *.php

foreach ($file in $files) {
    # Strip the '.php' from the end of the filename and make first character uppercase
#     $filename = $file.BaseName -replace '^(.)', { $_.Value.ToUpper() }
    $filename = $file.BaseName -replace "^(\w)", { $_.Value.ToUpper() }
   $f = " " + $file.BaseName[0]

 	$u = $f.ToUpper().trim()

    $filename = $file.BaseName -replace "^(\w)", {}
    # Construct new filename
    $newName = "Functions_${u}${filename}_Test.php"
    $className = "Functions_${u}${filename}_Test"
#     $className = "${u}${filename}"
    $FullName = $directory +"\"+ $newName

 echo $file.FullName

echo $FullName
    # Rename the file using git
    git mv $file.FullName $FullName


    # Get file content
    $content = Get-Content -Path $FullName

    # Strip the '.php' from the end of the file name
#     $className = ($file.BaseName -replace '(^[a-z])', {$args[0].Value.ToUpper()})

    # Create class name pattern
    $pattern = 'class\s+(.*)extends WP_UnitTestCase'

    # Replace class name
    $content = $content -replace $pattern, "class $className extends WP_UnitTestCase"

    # Write the new content back to the file
     $content | Set-Content -Path $FullName
}
