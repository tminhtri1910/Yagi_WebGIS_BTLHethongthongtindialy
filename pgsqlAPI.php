<?php
    if (isset($_POST['functionname']))
    {
        $paPDO = initDB();
        $paSRID = '4326';

        if (isset($_POST['paPoint'])) $paPoint = $_POST['paPoint'];
        $functionname = $_POST['functionname'];
        if (isset($_POST['date'])) $date = $_POST['date'];

        switch($functionname){
            case 'getGeoHighLightToAjax':
                $aResult = getGeoHighLightToAjax($paPDO, $paSRID, $paPoint);
                break;
            case 'getInfoToAjax':
                $aResult = getInfoToAjax($paPDO, $paSRID, $paPoint);
                break;
            case 'getGeoBuferToAjax':
                $aResult = getGeoBuferToAjax($paPDO, $date);
                break;
            case 'getGeoProvinceToAjax':
                $aResult = getGeoProvinceToAjax($paPDO);
                break;
            default:
                $aResult = "default null";
        } 

        // var_dump( $aResult); 
        echo $aResult;//trả về string
        
        closeDB($paPDO);
    }

    function initDB()
    {
        //Ket noi CSDL
        try{ 
            $paPDO = new PDO('pgsql:host=localhost;dbname=CSDLKhongGian;port=5432', 'postgres', 'Tmt@2003');
        }

        catch(PDOException $e){
            die("Loi ket noi CSDL: ".$e->getMessage());
        }
        return $paPDO;
    }

    function query ($paPDO, $paSQLStr)
    {
        try
        {
            //Khai bao exception
            $paPDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            //Su dung Prepare
            $stmt = $paPDO->prepare($paSQLStr);
            //Thuc thi cau truy van
            $stmt->execute();

            //Khai bao fetch kieu mang ket hop
            $stmt->setFetchMode(PDO::FETCH_ASSOC);

            //Lay danh sach ket qua
            $paResult = $stmt->fetchAll();
            return $paResult;
        }

        catch(PDOException $e){
            echo "Truy vấn thất bại: ".$e->getMessage();
            return null;
        }
    }

    function closeDB($paPDO){
        //Ngat ket noi
        $paPDO = null;
    }

    function getGeoHighLightToAjax ($paPDO, $paSRID, $paPoint)
    {
        //echo $paPoint;
        //echo "<br>";
        $paPoint = str_replace(',', ' ', $paPoint);

        $mySQLStr = "SELECT ST_AsGeoJson(geom) as geo 
                        FROM gadm41_vnm_1 JOIN vndamage ON vndamage.province = gadm41_vnm_1.name_1 
                        WHERE ST_Within(ST_GeomFromText('" . $paPoint . "', " . $paSRID . "), geom)
                    UNION ALL
                    SELECT ST_AsGeoJson(geom) as geo 
                        FROM gadm41_phl_1 JOIN pldamage ON pldamage.province = gadm41_phl_1.name_1
                        WHERE ST_Within(ST_GeomFromText('" . $paPoint . "', " . $paSRID . "), geom)";
        $result = query ($paPDO, $mySQLStr);
        
        if ($result != null)
        {
            return $result[0]['geo'];
        }
        else
            return "null";
    }

    function getInfoToAjax($paPDO, $paSRID,$paPoint)
    {
        $paPoint = str_replace(',', ' ', $paPoint);

        $mySQLStr = "SELECT name_1, number_of_deaths, number_of_injured, number_of_damaged_houses, number_of_flooded_houses, ST_Area(geom::geography)/1000000 as shape_area
                        FROM gadm41_vnm_1 JOIN vndamage ON vndamage.province = gadm41_vnm_1.name_1 
                        WHERE ST_Within(ST_GeomFromText('" . $paPoint . "', " . $paSRID . "), geom)
                    UNION ALL 
                    SELECT name_1, number_of_deaths, number_of_injured, number_of_damaged_houses, number_of_flooded_houses, ST_Area(geom::geography)/1000000 as shape_area
                        FROM gadm41_phl_1 JOIN pldamage ON pldamage.province = gadm41_phl_1.name_1
                        WHERE ST_Within(ST_GeomFromText('" . $paPoint . "', " . $paSRID . "), geom)";
        $result = query ($paPDO, $mySQLStr);

        if ($result != null)
        {
            $resFin = '<table>';
            // Lặp kết quả
            foreach ($result as $item) {
                // $resFin = $resFin.'<tr><td>GID_1: '.$item['gid_1'].'</td></tr>';
                $resFin = $resFin.'<tr><td>Tỉnh: '.$item['name_1'].'</td></tr>';
                $resFin = $resFin.'<tr><td>Số người chết: '.($item['number_of_deaths'] ?? 'Không có dữ liệu').'</td></tr>';
                $resFin = $resFin.'<tr><td>Số người bị thương: '.($item['number_of_injured'] ?? 'Không có dữ liệu').'</td></tr>';
                $resFin = $resFin.'<tr><td>Số nhà bị phá hủy: '.($item['number_of_damaged_houses'] ?? 'Không có dữ liệu').'</td></tr>';
                $resFin = $resFin. '<tr><td>Số nhà bị ngập: '.($item['number_of_flooded_houses'] ?? 'Không có dữ liệu').'</td></tr>';
                // $resFin = $resFin.'<tr><td>Chu vi: '.$item['shape_leng'] . '&deg' . '</td></tr>';
                $resFin = $resFin.'<tr><td>Diện tích: '.$item['shape_area'] . ' km&sup2;' . '</td></tr>';
                break;
            }
            $resFin = $resFin.'</table>';
            return $resFin;
        }
        else
            return "null";
    }

    function getGeoBuferToAjax($paPDO, $date)
    {
        //echo $paPoint;
        //echo "<br>";
        $mySQLStr = "SELECT ST_AsGeoJson(geom) as point, ST_AsGeoJson(ST_Buffer(geom::geography,3000*intensity)) as buffer,
                        ST_AsGeoJson(ST_Makeline(ARRAY(SELECT geom FROM yagistorm WHERE dtg <= '" . $date . "' ORDER BY gid ))) AS line
                     FROM yagistorm
                     WHERE dtg <= '".$date."'";
        $result = query($paPDO, $mySQLStr);
        // echo $result;
        if ($result != null) {
            $geoArray = []; // Initialize an array to hold all geo results
            // Lặp kết quả
            foreach ($result as $item) {
                $geoArray[] = json_decode($item['point']); // Decode each JSON string to GeoJSON object then add to the array
                $geoArray[] = json_decode($item['buffer']); // Decode each JSON string to GeoJSON object then add to the array
                $geoArray[] = json_decode($item['line']); // Decode each JSON string to GeoJSON object then add to the array
            }
            return json_encode($geoArray);  // Return the array as a JSON string
        } else
            return "null";
    }   

    function getGeoProvinceToAjax($paPDO)
    {
        $mySQLStr = "SELECT ST_AsGeoJson(geom) as province from vndamage 
                        JOIN gadm41_vnm_1 ON vndamage.province = gadm41_vnm_1.name_1 
                    UNION ALL
                    SELECT ST_AsGeoJson(geom) as province from pldamage
                        JOIN gadm41_phl_1 ON pldamage.province = gadm41_phl_1.name_1";
        $result = query($paPDO, $mySQLStr);
        // return $result;
        if ($result != null) {
            $geoArray = []; // Initialize an array to hold all geo results
            // Lặp kết quả
            foreach ($result as $item) {
                $geoArray[] = json_decode($item['province']); // Decode each JSON string to GeoJSON object then add to the array
            }
            return json_encode($geoArray);  // Return the array as a JSON string
        } else
            return "null";
    }   
?>