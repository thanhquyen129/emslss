<?php
include '../config/db.php';

$order_id = intval($_POST['order_id']);

foreach($_FILES['image']['tmp_name'] as $k=>$tmp){

    $name = time().'_'.$k.'_'.basename($_FILES['image']['name'][$k]);

    move_uploaded_file(
        $tmp,
        "../assets/uploads/".$name
    );

    $stmt = $conn->prepare("
    INSERT INTO emslss_images(order_id,image_path,uploaded_by)
    VALUES(?,?,1)
    ");

    $stmt->bind_param("is",$order_id,$name);
    $stmt->execute();
}

header("Location: order_detail.php?id=".$order_id);
exit;
?>