<?php
/**
 * Files will be stored at e.g. https://storage.googleapis.com/<appspot site url>/testthis.txt
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/env.php';

use Google\Cloud\Storage\StorageClient;

$app = [];
$app['bucket_name'] = "cloudnotes-323921.appspot.com";
$app['mysql_user'] = $mysql_user;
$app['mysql_password'] = $mysql_password;
$app['mysql_dbname'] = "cloudnotes";
$app['project_id'] = getenv('GCLOUD_PROJECT');

function googleConfig($googleAuth)
{
    putenv("GOOGLE_APPLICATION_CREDENTIALS={$googleAuth}");
}

function basePath($tail = '')
{
    return __DIR__ . '/' . $tail;
}

googleConfig(basePath('google-api.json'));

//$storage = new StorageClient();
//$storage->registerStreamWrapper();

$servername = '35.233.30.185';
$username = $app['mysql_user'];
$password = $app['mysql_password'];
$dbname = $app['mysql_dbname'];
$dbport = null;

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $dbport); // , 
//    "/cloudsql/cloudnotes-323921:europe-west1:cloudnotes-323921");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "\nConnected successfully\n";

/**
 * Upload a file.
 *
 * @param string $bucketName the name of your Google Cloud bucket.
 * @param string $objectName the name of the object.
 * @param string $source the path to the file to upload.
 *
 */
function upload_object($bucketName, $objectName, $source)
{
    $storage = new StorageClient();
    $storage->registerStreamWrapper();
    $file = fopen($source, 'r');
    $bucket = $storage->bucket($bucketName);
    $object = $bucket->upload($file, [
        'name' => $objectName
    ]);
    printf('Uploaded %s to gs://%s/%s' . PHP_EOL, basename($source), $bucketName, $objectName);
    
    return $objectName;
}

// delete file
if (! empty($_GET['file_id'])) {
    $fileSql = "SELECT * FROM uploaded_files where id = " . $_GET['file_id'];
    $files = $conn->query($fileSql);
    while ($file = $files->fetch_assoc()) {
        $sql = "DELETE FROM uploaded_files WHERE id=" . $_GET['file_id'];
        
        if ($conn->query($sql) === true) {
            echo "Record deleted successfully";
        }
        
        header('Location:index.php');
        exit;
    }
}

// delete note
if (! empty($_GET['note_id'])) {
    $noteSql = "SELECT * FROM notes where note_id = " . $_GET['note_id'];
    $noteSql = $conn->query($noteSql);
    while ($note = $noteSql->fetch_assoc()) {
        $sql = "DELETE FROM uploaded_files WHERE note_id=" . $_GET['note_id'];
        
        if ($conn->query($sql) === true) {
            $sql2 = "DELETE FROM notes WHERE note_id=" . $_GET['note_id'];
            
            if ($conn->query($sql2) === true) {
                echo "Record deleted successfully";
            }
            
            header('Location:index.php');
            exit;
        }
    }
}

if (! empty($_POST['note']) && ! empty($_FILES['uploaded_files'])) {
    // insert query
    $sql = "INSERT INTO notes (note_message, note_createdOn) VALUES ('" . $_POST['note'] . "', '" . date('Y-m-d H:i:s') . "')";
    if ($conn->query($sql) === true) {
        $last_id = $conn->insert_id;
        echo "New record created successfully";
        
        foreach ($_FILES["uploaded_files"]["tmp_name"] as $key => $tmp_name) {
            $file_name = $_FILES["uploaded_files"]["name"][$key];
            $file_tmp = $_FILES["uploaded_files"]["tmp_name"][$key];
            if ($_FILES["uploaded_files"]["type"][$key] !== "text/plain") {
                echo "File must be a .txt";
            } else {
                $file_handle = fopen($_FILES['uploaded_files']['tmp_name'][$key], 'r');
                
                $uploadedFileName = upload_object($app['bucket_name'],
                    $_FILES['uploaded_files']['name'][$key],
                    $_FILES['uploaded_files']['tmp_name'][$key]
                );
                
                $sql = "INSERT INTO uploaded_files (note_id, file_name) VALUES ('" . $last_id . "', '" . $uploadedFileName . "')";
                if ($conn->query($sql) === true) {
                    echo "File saved.";
                } else {
                    echo "Error: " . $sql . "<br>" . $conn->error;
                }
            }
        }
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
?>


<form action="index.php" enctype="multipart/form-data" method="post">
  <input type="text" name="note" placeholder="Add note here">
  <br>

  Files to upload: <br>
  <input type="file" name="uploaded_files[]" multiple size="40">
  <input type="submit" value="Send">
</form>

<?php
$sql = "SELECT * FROM notes";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
// output data of each row
    while ($row = $result->fetch_assoc()) {
        // output data of each row
        while ($row = $result->fetch_assoc()) {
          
        echo "<a href='index.php?note_id=" . $row["note_id"] . "'>X</a></br>";
            echo "<table><tr><th>Note ID</th><th>Note</th><th>Files</th><th>Note Created On</th></tr>";
            echo "<tr><td>" . $row["note_id"] . "</td><td>" . $row["note_message"] . "</td><td>";
            
            $fileSql = "SELECT * FROM uploaded_files where note_id = " . $row["note_id"];
            $files = $conn->query($fileSql);
            while ($file = $files->fetch_assoc()) {
                echo "<a target='_blank' href='https://storage.googleapis.com/cloudnotes-323921.appspot.com/" . $file['file_name'] . "'>" . $file['file_name'] . "</a>" . "&nbsp;&nbsp;&nbsp;<a href='index.php?file_id=" . $file['id']
                    . "'>X</a> <br>";
            }
            echo "</td><td>" . $row["note_createdOn"] . "</td></tr>";
            echo "</table>";
        }
    }
} else {
    echo "0 results";
}

mysqli_close($conn);

?>

<style>
    table {
        width: 1200px;
        margin-bottom: 10px;
    }

    table, th, td {
        border: 1px solid black;
    }

    th, td {
        padding: 10px;
        width: 25%;
    }
</style>
