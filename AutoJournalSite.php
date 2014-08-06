<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no">
    <meta charset="utf-8">
    <style type="text/css">
      html, body {
        height: 100%;
        margin: 0px;
        padding: 0px;
        font-family: Roboto;
      }
			
			#mapCanvas {
        height: 925px;
				width: 1680px;
        margin: 0px;
        padding: 0px;
        font-family: Roboto;
      }
			
			h1 {
				display: block;
				font-size: 1.2em;
				-webkit-margin-before: 0px;
				-webkit-margin-after: 0.2em;
				-webkit-margin-start: 0.8em;
				-webkit-margin-end: 0.2em;
			}
			
			p {
        margin-top: 4px;
        margin-bottom: 12px;
				margin-left: 16px;
				font-size: .6em;
			}
			
      .controls {
        margin-top: 4px;
        margin-bottom: 12px;
				margin-left: 16px;
        border: 1px solid transparent;
        border-radius: 2px 0 0 2px;
        box-sizing: border-box;
        -moz-box-sizing: border-box;
        height: 32px;
        outline: none;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
      }
			
			#bottomBar {
				position: relative;
				left: 0px;
				top: -1175px;
				height: 250px;
				width: 1410px;
				background: rgba(255,255,255,0.7);
				z-index: 1;
				outline: none;
			}
			
			#sidebar {
				position: relative;
				left: 1410px;
				top: -925px;
				height: 925px;
				width: 270px;
				background: rgba(255,255,255,0.7);
				z-index: 2;
				outline: none;
				padding: 10;
			}
			
      input {
        background-color: #fff;
        padding: 0 11px 0 8px;
        font-family: Roboto;
        font-size: 15px;
        font-weight: 300;
        text-overflow: ellipsis;
      }
			
			input[type="text"] {
				width: 240px;
			}
			input[type="date"] {
				width: 128px;
				padding-right: 0px;
			}
			input[type="time"] {
				width: 92px;
				padding-right: 0px;
			}
			input[type="checkbox"] {
				margin-top: 4px;
        margin-bottom: 12px;
				margin-left: 16px;
        border: 1px solid transparent;
        border-radius: 2px 0 0 2px;
        outline: none;
			}
			
			input::-webkit-outer-spin-button,
			input::-webkit-inner-spin-button{
				-webkit-appearance: none;
				display: none;
			}

			button {
				float: right;
        margin-bottom: 4px;
        border: 1px solid transparent;
        border-radius: 2px 0 0 2px;
        box-sizing: border-box;
        -moz-box-sizing: border-box;
        height: 24px;
        outline: none;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
      }
    </style>
    <title>AutoJournal</title>
		<script type="text/javascript"
      src="https://maps.googleapis.com/maps/api/js?v=3.exp&libraries=places"> //"https://maps.googleapis.com/maps/api/js?key=AIzaSyDuzuNuG6mRj6N9f3GJWMg7EP3ZKHAdfFA">
    </script>
    <script>
    	//look for TODO and POTENTIAL tags


    	var MONTH_REF_NO_NUMS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
					
			var STANFORD_LOCATION = new google.maps.LatLng(37.424188, -122.166349);
			var MIN_WIDTH = 1280; var MIN_HEIGHT = 800;
			var MAX_BUCKETS = 330; // divisor of 1320 = 2 x 2 x 2 x 3 x 5 x 11
			var MAX_CIRCLES_DISPLAYABLE = 2000; 

			var windowWidth = window.innerWidth;
			var windowHeight = window.innerHeight;
			var bottomBarWidth;
			
			var map;

			var allLogsByDay = [];
			var totalNumLogs = 0;

			var searchCircle = null;
			var isDefiningCustomRegion = false;
			var searchPolyline = null;
			var searchPolylineMarkers = [];
			searchPolylineMarkers.push([]);
			var searchCustomRegions = [];

			var searchStartDate = null;
			var searchEndDate = null;

			var fraction = 1;
			var circlesToDraw = [];
			var displayCircleRadius = 100;
			var displayCircleOpacity = .1; 

			var buckets = [];
			

			function resize(){
				//TODO fix - this method doesn't work as intended
				windowWidth = window.innerWidth;
				windowHeight = window.innerHeight;
				
				if(window.innerWidth < MIN_WIDTH) {
					windowWidth = MIN_WIDTH;
				}
				if(window.innerHeight < MIN_HEIGHT) {
					windowHeight = MIN_HEIGHT;
				}
				
				document.getElementById("mapCanvas").width = windowWidth;
				document.getElementById("mapCanvas").height = windowHeight;
				
				google.maps.event.trigger(map, "resize");
			}
			function getMousePos(canvas, evt) {
        var rect = canvas.getBoundingClientRect();
        return {
          x: evt.clientX - rect.left,
          y: evt.clientY - rect.top
        };
      }


			//init
			function initializeMap(){
				var mapOptions = {
					mapTypeId: google.maps.MapTypeId.ROADMAP,
					center: STANFORD_LOCATION,
					zoom: 15,
					
					panControl: false,
					zoomControl: false,
					streetViewControl: false,
					mapTypeControl: false
				};
				map = new google.maps.Map(document.getElementById("mapCanvas"), mapOptions);

				//create the search box and link it to the UI element.
				var input = (document.getElementById("searchBox"));
				var searchBox = new google.maps.places.SearchBox((input));

				//set up the polyline
				var polyOptions = {
			    strokeColor: '#0000FF',
			    strokeOpacity: 1,
			    strokeWeight: 1
			  };
			  searchPolyline = new google.maps.Polyline(polyOptions);
			  searchPolyline.setMap(map);

				//listen for the event fired when the user selects an item 
				google.maps.event.addListener(searchBox, "places_changed", function() {
					var places = searchBox.getPlaces();
					if (places.length == 0) return;
					map.panTo(places[0].geometry.location);
				});

				//bias the search results towards places nearby
				google.maps.event.addListener(map, "bounds_changed", function() {
					var bounds = map.getBounds();
					searchBox.setBounds(bounds);
				});
				
				//add a listener for map clicks to make the search circle
				google.maps.event.addListener(map, "click", function(event) {
					if(!isDefiningCustomRegion){
						updateSearchCircle(event.latLng);
						refreshCircleList();
						updateBuckets();
					}
					else
						updatePolyline(event.latLng);
				});

				google.maps.event.addListener(map, "mousemove", function(event) {
					refreshCircleMouseoverDisplay(event.latLng);
				});

				allLogsByDay = convertAllFilesContentsToLogArrays(loadAllTextFiles());
				updateBuckets();

				refreshCircleList();
				drawGraph(-1);
			}
			function initializeSidebar(){
				resetTime();
				document.getElementById("sidebar").addEventListener('mousemove', function(event) {
	        setStatisticsDisplay3("");
      	}, false);
			}
			function initializeBottomBar(){
				var bottomBarWidth = (/*windowWidth*/ 1680 - 270);
				document.getElementById("bottomBar").style.width = bottomBarWidth + "px";

				var graphCanvas = document.getElementById("bottomCanvas");
				graphCanvas.width = bottomBarWidth - 5;

				graphCanvas.addEventListener('mousemove', function(event) {
	        var mousePos = getMousePos(graphCanvas, event);
	        updateGraphToShowInfo(graphCanvas, mousePos);
      	}, false);
			}
			

			//load and deal with actual files
			function loadAllTextFiles(){
				<?php
					$monthReference = array("01 January", "02 February", "03 March", "04 April", "05 May", "06 June", "07 July", "08 August", "09 September", "10 October", "11 November", "12 December");
					$phpAllFileContents = array();
					for($year = 2000; $year < 2200; $year++){
						if(!file_exists("Location Logs/{$year}")) continue;
						for($month = 0; $month < 12; $month++){
							$monthStr = $monthReference[$month];
							if(!file_exists("Location Logs/{$year}/{$monthStr}")) continue;
							for($dom = 1; $dom < 32; $dom++){
								$domStr = str_pad($dom + "", 2, "00", STR_PAD_LEFT);
								if(!file_exists("Location Logs/{$year}/{$monthStr}/{$domStr}.txt")) continue;
								$phpFileContents = file_get_contents("Location Logs/{$year}/{$monthStr}/{$domStr}.txt"); //file_get_contents("Location Logs/2014/07 July/14.txt");
								$phpAllFileContents[] = $phpFileContents;
							}
						}
					}
				?>
				var fileContentsCompressed = <?php echo json_encode($phpAllFileContents); ?>;
				var fileContents = [];
				for(var i = 0; i < fileContentsCompressed.length; i++){
					fileContents.push(fileContentsCompressed[i].split("\n"));
				}
				
				return fileContents;
			}
			function convertAllFilesContentsToLogArrays(allFileContents){
				var allLogs = [];
				totalNumLogs = 0;
				
				for(var day = 0; day < allFileContents.length; day++){;
					var dayLogs = [];
					totalNumLogs += allFileContents[day].length - 2;
					
					for(var log = 2; log < allFileContents[day].length; log++){
						var halves = allFileContents[day][log].split(" :: ");
						var currLog = {
							location: stringToLocation(halves[1].trim()),
							time: halves[0].trim()
						};
						dayLogs.push(currLog);
					}
					var dayData = {
						logs: dayLogs,
						date: stringToDate(allFileContents[day][0])
					};
					allLogs.push(dayData);
				}
				
				return allLogs;
			}
			

			//validate and use new input to the search parameters etc.
			//time
			function resetTime(){
				searchStartDate = new Date(2013, 5, 3, 19, 30, 0, 0);

				document.getElementById("startDate").value = searchStartDate.getFullYear() + "-" + ("00" + (searchStartDate.getMonth() + 1)).slice(-2)  + "-" + ("00" + searchStartDate.getDate()).slice(-2);
				document.getElementById("startTime").value = ("00" + searchStartDate.getHours()).slice(-2) + ":" + ("00" + searchStartDate.getMinutes()).slice(-2);

				searchEndDate = new Date();
				searchEndDate.setMinutes(Math.floor(searchEndDate.getMinutes() / 5) * 5);

				document.getElementById("endDate").value = searchEndDate.getFullYear() + "-" + ("00" + (searchEndDate.getMonth() + 1)).slice(-2)  + "-" + ("00" + searchEndDate.getDate()).slice(-2);
				document.getElementById("endTime").value = ("00" + searchEndDate.getHours()).slice(-2) + ":" + ("00" + searchEndDate.getMinutes()).slice(-2);

				refreshCircleList();
				updateBuckets();
			}
			function changeStartDate(){
				var startDate = document.getElementById("startDate").value;
				var startTime = document.getElementById("startTime").value;

				var dateValues = startDate.split("-");
				var timeValues = startTime.split(":");
				
				searchStartDate = new Date(parseInt(dateValues[0]), parseInt(dateValues[1])-1, parseInt(dateValues[2]), parseInt(timeValues[0]), parseInt(timeValues[1]), 0, 0);

				refreshCircleList();
				updateBuckets();
				drawGraph(-1);
			}
			function changeEndDate(){
				var endDate = document.getElementById("endDate").value;
				var endTime = document.getElementById("endTime").value;

				var dateValues = endDate.split("-");
				var timeValues = endTime.split(":");
				
				searchEndDate = new Date(parseInt(dateValues[0]), parseInt(dateValues[1])-1, parseInt(dateValues[2]), parseInt(timeValues[0]), parseInt(timeValues[1]), 0, 0);

				refreshCircleList();
				updateBuckets();
			}

			//location
			function updateSearchCircle(center){
				var radius = 100;
				if(searchCircle != null) {
					radius = searchCircle.getRadius();
					searchCircle.setMap(null);
				}
				
				var circleOptions = {
					strokeColor: "#0000FF",
					strokeOpacity: 0,
					strokeWeight: 1,
					fillColor: "#0000FF",
					fillOpacity: 0.15,
					map: map,
					center: center,
					radius:	radius,
					editable: true,
					geodesic: true
				};
				
				searchCircle = new google.maps.Circle(circleOptions);
				setRadiusInTextBox(searchCircle.getRadius());
				setCoordinatesInTextBox(searchCircle.getCenter());
				
				google.maps.event.addListener(searchCircle, "radius_changed", function() {
					setRadiusInTextBox(searchCircle.getRadius());
					refreshCircleList();
					updateBuckets();
				});
				google.maps.event.addListener(searchCircle, "center_changed", function() {
					setCoordinatesInTextBox(searchCircle.getCenter());
					refreshCircleList();
					updateBuckets();
				});
			}
			function updateSearchCircleBasedOnCustomRegion(){
				//TODO update fields in sidebar based on center, and radius with the distance to the farthest point

				refreshCircleList();
				updateBuckets();
			}
			function updatePolyline(clickLoc){
				var path = searchPolyline.getPath();
			  path.push(clickLoc);

			  var marker = new google.maps.Marker({
			    position: clickLoc,
			    title: 'Vertex ' + path.getLength(),
			    icon: {
			      path: google.maps.SymbolPath.CIRCLE,
			      scale: 3
			    },
			    draggable: true,
			    map: map
			  });

				google.maps.event.addListener(marker,'click',function(event) {
					closeCustomRegion(marker);
				});

				google.maps.event.addListener(marker,'drag',function(event) {
					dragPolylineMarker(marker);
				});

				searchPolylineMarkers[searchPolylineMarkers.length-1].push(marker);
			}
			function closeCustomRegion(marker){
				var path = searchPolyline.getPath();
				searchPolyline.setMap(null);
				var polyOptions = {
			    strokeColor: '#0000FF',
			    strokeOpacity: 1,
			    strokeWeight: 1
			  };
			  searchPolyline = new google.maps.Polyline(polyOptions);
			  searchPolyline.setMap(map);

				var polygonOptions = {
			    map: map,
			    paths: path,
			    strokeColor: '#0000FF',
			    strokeOpacity: 0,
			    strokeWeight: 1,
			    fillColor: '#0000FF',
			    fillOpacity: 0.15,
			    draggable: true,
			    geodesic: true
			  };

				var searchCustomRegion = new google.maps.Polygon(polygonOptions);

				google.maps.event.addListener(searchCustomRegion,'drag',function(event) {
					dragCustomRegion();
				});

				searchCustomRegions.push(searchCustomRegion);

				updateSearchCircleBasedOnCustomRegion();
			}
			function dragCustomRegion(){
				//TODO should change markers

				updateSearchCircleBasedOnCustomRegion();
			}
			function dragPolylineMarker(marker){
				//TODO should change polyline if it exists, and custom region if it does - but how?

				updateSearchCircleBasedOnCustomRegion();
			}
			function resetLocation(){
				document.getElementById("searchBox").value = "";
				document.getElementById("searchCoordinates").value = "";
				document.getElementById("searchRadius").value = "";

				if(searchCircle != null){
					searchCircle.setMap(null);
					searchCircle = null;
				}

				searchPolyline.setMap(null);
				var polyOptions = {
			    strokeColor: '#0000FF',
			    strokeOpacity: 1,
			    strokeWeight: 1
			  };
			  searchPolyline = new google.maps.Polyline(polyOptions);
			  searchPolyline.setMap(map);

			  for(var i = 0; i < searchPolylineMarkers.length; i++){
			  	for(var j = 0; j < searchPolylineMarkers[i].length; j++){
						searchPolylineMarkers[i][j].setMap(null);
					}
				}
				searchPolylineMarkers = [];
				searchPolylineMarkers.push([]);

				for(var i = 0; i < searchCustomRegions.length; i++){
					searchCustomRegions[i].setMap(null);
				}
				searchCustomRegions = [];

				refreshCircleList();
				updateBuckets();
			}
			function validateRadius(){
				var radius = document.getElementById("searchRadius").value;
				var numRadius = parseInt(radius);
				
				if(isNaN(numRadius)){
					if(searchCircle == null) setRadiusInTextBox(-1);
					else setRadiusInTextBox(searchCircle.getRadius());
				}
				else {
					if(searchCircle == null)
						updateSearchCircle(map.getCenter());
					searchCircle.setRadius(numRadius);

					refreshCircleList();
					updateBuckets();
				}
			}
			function validateCoordinates(){
				var center = document.getElementById("searchCoordinates").value.trim();
				var coords = center.split(" ");
				
				if(coords.length == 2) {
					var lat = parseFloat(coords[0]);
					var lng = parseFloat(coords[1]);
					
					if(!isNaN(lat) && !isNaN(lng)) {
						var loc = coordsToLocation(lat, lng);
						updateSearchCircle(loc);
						map.panTo(loc);
						return;
					}
				}
				
				if(searchCircle == null) setCoordinatesInTextBox(null);
				else {
					setCoordinatesInTextBox(searchCircle.getCenter());

					refreshCircleList();
					updateBuckets();
				}
			}
			function toggleDefineCustomRegion(){
				resetLocation();
				isDefiningCustomRegion = !isDefiningCustomRegion;
			}

			//settings
			function changeDisplayRadius(){
				var temp = parseInt(document.getElementById("displayRadius").value);
				if(isNaN(temp)){
					document.getElementById("displayRadius").value = "";
				}
				else{
					displayCircleRadius = temp;
					for(var i = 0; i < circlesToDraw.length; i++)
						circlesToDraw[i].setRadius(displayCircleRadius);
				}
			}
			function changeDisplayOpacity(){
				var temp =  parseFloat(document.getElementById("displayOpacity").value);
				if(isNaN(temp)){
					document.getElementById("displayOpacity").value = "";
				}
				else{
					displayCircleOpacity = temp;
					refreshCircleList();
				}
			}	
			function toggleConnections(){
				//TODO draw lines between the regions that are far away from each other
			}


			//get logs ready as circles to draw
			function refreshCircleList(){
				for(var i = 0; i < circlesToDraw.length; i++){
					circlesToDraw[i].setMap(null);
				}
				circlesToDraw = [];
				
				var distinctDays = 0;
				var locationsWithinRegion = [];

				var bounds = null;
				if(searchCircle != null) bounds = searchCircle.getBounds();
				if(searchCustomRegions.length != 0) bounds = searchCustomRegions[0].getBounds(); //TODO dependent on whether we're keeping this an array...?

				var startTime = searchStartDate.getTime();
				var endTime = searchEndDate.getTime();

				//TODO fix this time in detail beyond the raw day
				for(var day = 0; day < allLogsByDay.length; day++){
					var thisDaysTime = allLogsByDay[day].date.getTime();
					if(thisDaysTime + 60*60*1000 < startTime) continue;
					if(thisDaysTime - 60*60*1000 > endTime) break;
					
					//TODO check travel circle for the day here
					var logToday = false;
					for(var log = 0; log < allLogsByDay[day].logs.length; log++){
						var nextLoc = allLogsByDay[day].logs[log].location;
						if((searchCircle == null && searchCustomRegions.length == 0) || bounds.contains(nextLoc)){
							locationsWithinRegion.push(nextLoc);
							logToday = true;
						}
					}
					if(logToday) distinctDays++;
				}

				fraction = Math.floor(locationsWithinRegion.length/MAX_CIRCLES_DISPLAYABLE) + 1;

				var counter = -1;
				for(var i = 0; i < locationsWithinRegion.length; i++){
					counter++;
					if(counter % fraction != 0) continue;

					var circleOptions = {
						strokeColor: "#FF0000",
						strokeOpacity: 0,
						strokeWeight: 1,
						fillColor: "#FF0000",
						fillOpacity: displayCircleOpacity,
						map: map,
						center: locationsWithinRegion[i],
						radius:	displayCircleRadius,
						geodesic: true
					};

					var currCircle = new google.maps.Circle(circleOptions);

					google.maps.event.addListener(currCircle, "mousemove", function(event) {
						refreshCircleMouseoverDisplay(event.latLng);
					});

					circlesToDraw.push(currCircle);
				}

				setStatisticsDisplay2(distinctDays, circlesToDraw.length);
			}
			function refreshCircleMouseoverDisplay(mouseLoc){
				var circlesAround = [];
				var firstIndex = 0;
				for(var index = 0; index < circlesToDraw.length; index++)
				{
					if(circlesToDraw[index].getBounds().contains(mouseLoc)){
						circlesAround.push(circlesToDraw[index]);
						firstIndex = index;
					}
				}

				if(circlesAround.length == 0){
					setStatisticsDisplay3("");
				}
				else if(circlesAround.length == 1){
					firstIndex *= fraction;
					for (var day = 0; day < allLogsByDay.length; day++) {
						if(allLogsByDay[day].logs.length <= firstIndex){
							firstIndex -= allLogsByDay[day].logs.length;
						}
						else{
							var currTime = allLogsByDay[day].logs[firstIndex].time;
							var currDate = allLogsByDay[day].date;
							currDate = addTimeStrToDate(currDate, currTime);
							setStatisticsDisplay3("This log is from " + currDate.toLocaleTimeString() + " on " + currDate.toLocaleDateString());
							break;
						}
					}
				}
				else{
					setStatisticsDisplay3("There are " + circlesAround.length + " logs displaying in this region");
				}
			}


			//bottom bar graph functions
			function drawGraph(bucketSelected){
				//TODO incorporate MAX_BUCKETS
				var totNumBuckets = buckets.length;
				var totLength = 1320;
				var maxHeight = 100;

				var maxNumLogs = 1;
				for(var i = 0; i < totNumBuckets; i++){
					var potMax = 0;
					if(buckets[i].logIndex != -1) {
						potMax = allLogsByDay[buckets[i].logIndex].logs.length;
					}
					if(potMax > maxNumLogs) maxNumLogs = potMax;
				}

				var canvas = (document.getElementById("bottomCanvas"));
				var context = canvas.getContext("2d");
				context.fillRect(32, 175, totLength + 20, 3); //should be 1320 long from (42, 175) to (1362, 175)

				if(bucketSelected == -1) context.fillStyle = "#444444";
				else context.fillStyle = "#666666";

				//TODO no it's not vvv
				alert("graph drawing: totBuckets = " + totNumBuckets);

				for(var i = 0; i < totNumBuckets; i++){
					if(bucketSelected == i) context.fillStyle = "#222222";
					var currNumLogs = 0;
					if(buckets[i].logIndex != -1) currNumLogs = allLogsByDay[buckets[i].logIndex].logs.length;
					drawBucket(context, i, (currNumLogs / maxNumLogs) * maxHeight, totNumBuckets, totLength);
					if(bucketSelected == i) context.fillStyle = "#666666"; //POTENTIAL could draw red box here to show bucket when number is 0
				}
			}
			function updateGraphToShowInfo(graphCanvas, mousePos){
				graphCanvas.width = graphCanvas.width;

				var bucketSelected = -1;
				if(mousePos.x >= 42 && mousePos.x < 1362 && mousePos.y >= 75 && mousePos.y < 175){
					bucketSelected = Math.floor(((mousePos.x - 42) / 1320) * buckets.length);
				}

				drawGraph(bucketSelected);
				if(bucketSelected != -1){
					//TODO change toDateString()
					var info = buckets[bucketSelected].date.toDateString() + "\n";
					if(buckets[bucketSelected].logIndex == -1) info += "0";
					else info += allLogsByDay[buckets[bucketSelected].logIndex].logs.length;
					info += " logs";
					setStatisticsDisplay3(info);
				}
				else setStatisticsDisplay3("");
			}
			function updateBuckets(){
				buckets = [];

				var allLogsIndex = 0;
				for(var year = searchStartDate.getFullYear(); year <= searchEndDate.getFullYear(); year++){
					for(var month = 0; month < 12; month++){
						if(year == searchStartDate.getFullYear() && month < searchStartDate.getMonth()) continue;
						if(year == searchEndDate.getFullYear() && month > searchEndDate.getMonth()) break;
						for(var dom = 1; dom < 32; dom++){
							if(year == searchStartDate.getFullYear() && month == searchStartDate.getMonth() && dom < searchStartDate.getDate()) continue;
							if(year == searchEndDate.getFullYear() && month == searchEndDate.getMonth() && dom > searchEndDate.getDate()) break;

							var currDate = makeCleanDate(year, month, dom);
							if(dom != currDate.getDate()) {
								continue;
							}

							var bucket = {
								date: currDate,
								logIndex: -1
							};

							if(allLogsIndex < allLogsByDay.length && areDatesEqual(currDate, allLogsByDay[allLogsIndex].date)){
								bucket.logIndex = allLogsIndex;
								allLogsIndex++;
							}

							buckets.push(bucket);
						}
					}
				}

				setStatisticsDisplay(buckets.length, totalNumLogs);
			}
			function drawBucket(context, bucket, height, totNumBuckets, totLength){ //(totLength / totNumBuckets) should be an integer. totLength is like 1320, not 1340 - no padding
				context.fillRect(42 + (totLength / totNumBuckets) * bucket, 175 - height, (totLength / totNumBuckets), height);
			}


			//set values in sidebar based on inside changes
			function setRadiusInTextBox(radius){
				if(radius < 0)
					document.getElementById("searchRadius").value = "";
				else {
					radius = Math.floor(radius + .5);
					document.getElementById("searchRadius").value = radius;
				}
			}
			function setCoordinatesInTextBox(center){
				if(center == null)
					document.getElementById("searchCoordinates").value = "";
				else 
					document.getElementById("searchCoordinates").value = locationToString(center);
			}
			function setStatisticsDisplay(numDays, numLogs){
				document.getElementById("currentStats").innerHTML = "There are a total of " + numLogs + " logs over a range of " + numDays + " days";
			}
			function setStatisticsDisplay2(numDays, numLogs){ 
				if(fraction <= 1)
					document.getElementById("currentStats2").innerHTML = "Displaying " + numLogs + " logs from " + numDays + " particular days";
				else
					document.getElementById("currentStats2").innerHTML = "Displaying " + numLogs + " (1/" + fraction + ") logs from " + numDays + " particular days";
			}
			function setStatisticsDisplay3(time){
				document.getElementById("currentStats3").innerHTML = time + "";
			}
			

			//constructors
			function makeCleanDate(year, month, day){
				return new Date(year, month, day, 0, 0, 0, 0);
			}
			function addTimeStrToDate(existingDate, timeStr){
				var segments = timeStr.trim().split(":");
				existingDate.setHours(parseInt(segments[0]));
				existingDate.setMinutes(parseInt(segments[1]));
				existingDate.setSeconds(parseInt(segments[2]));
				return existingDate;
			}


			//comparisons
			function areDatesEqual(date1, date2){
				return ((date1.getFullYear() == date2.getFullYear()) && (date1.getMonth() == date2.getMonth()) && (date1.getDate() == date2.getDate()));
			}


			//type conversions
			function locationToString(loc){
				var lat = (Math.floor(loc.lat() * 1000000 + .5) / 1000000) + "";
				if(lat.indexOf(".") == -1) lat += ".";
				lat = (lat + "000000").slice(0, 7 + lat.indexOf("."));
				
				var lng = (Math.floor(loc.lng() * 1000000 + .5) / 1000000) + "";
				if(lng.indexOf(".") == -1) lng += ".";
				lng = (lng + "000000").slice(0, 7 + lng.indexOf("."));
				
				return lat + ", " + lng;
			}
			function stringToLocation(str){
				var coords = str.trim().split(" ");
				var lat = parseFloat(coords[0]);
				var lng = parseFloat(coords[1]);
				return coordsToLocation(lat, lng);
			}
			function coordsToLocation(lat, lng){
				return new google.maps.LatLng(lat, lng);
			}
			function stringToDate(str){
				var parts = str.split(" ");

				var year = parseInt(parts[3]);
				var month = 0;
				for(var monthInd = 1; monthInd < 12; monthInd++){
					if(MONTH_REF_NO_NUMS[monthInd] == parts[1]){
						month = monthInd;
						break;
					}
				}
				var date = parseInt(parts[2].slice(0,-3));

				return makeCleanDate(year, month, date);
			}

			google.maps.event.addDomListener(window, "load", initializeMap);		
		</script>
	</head>
  <body> <!--   <body onresize="resize()">   -->
    <div id="mapCanvas"></div>
		<div id="sidebar">
			<form>
				<div>
					<br>
					<table>
						<tr>
							<th>
								<h1>Time</h1>
							</th>
							<th width="187px">
								<button type="button" id="timeReset" onclick="resetTime()">Reset</button>
							</th>
						</tr>
					</table>
					<input type="date" id="startDate" class="controls" onchange="changeStartDate()" required="required"> 
					<input type="time" id="startTime" class="controls" onchange="changeStartDate()" required="required">
					<input type="date" id="endDate" class="controls" onchange="changeEndDate()" required="required"> 
					<input type="time" id="endTime" class="controls" onchange="changeEndDate()" required="required">
				</div>
				<div>
					<br>
					<table>
						<tr>
							<th>
								<h1>Location</h1>
							</th>
							<th width="155px">
								<button type="button" id="locationReset" onclick="resetLocation()">Reset</button>
							</th>
						</tr>
					</table>
					<input type="text" id="searchBox" class="controls" placeholder="Search">
					<input type="text" id="searchCoordinates" class="controls" onchange="validateCoordinates()" placeholder="Coordinates" required="required">
					<input type="text" id="searchRadius" class="controls" onchange="validateRadius()" placeholder="Radius (ft)" required="required">
					<input type="checkbox" id="connections" onchange="toggleDefineCustomRegion()">Define custom region</input>
				</div>
				<div>
					<br>
					<h1>Settings</h1>
					<input type="text" id="displayRadius" class="controls" onchange="changeDisplayRadius()" placeholder="Display Radius (default 100 ft)">
					<input type="text" id="displayOpacity" class="controls" onchange="changeDisplayOpacity()" placeholder="Display Opacity (default .1)">
					<input type="checkbox" id="connections" onchange="toggleConnections()">Draw connections</input>
				</div>
				<div>
					<br><br><br>
					<p id="currentStats"><p>
					<p id="currentStats2"><p>
					<p id="currentStats3"><p>
				</div>
			</form>
		</div>
		<div id="bottomBar">
			<canvas id="bottomCanvas" height= "245"></canvas>
		</div>
		
		<script>
			initializeSidebar();
			initializeBottomBar();
		</script>
  </body>
</html>