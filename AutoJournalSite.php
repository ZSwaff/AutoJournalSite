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
				top: -1075px;
				height: 150px;
				width: 1410px;
				background: rgba(255,255,255,0.55);
				z-index: 1;
				outline: none;
			}
			
			#sidebar {
				position: relative;
				left: 1410px;
				top: -925px;
				height: 925px;
				width: 270px;
				background: rgba(255,255,255,0.85);
				z-index: 2;
				outline: none;
				padding: 10;
				box-shadow: -3px 0px 25px 5px rgba(0, 0, 0, 0.25);
			}

			#timeReset, #locationReset {
				float: right;
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
			var MAX_BUCKETS = 660;
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
			var isDrawingConnections = false;
			var connectionsToDraw = [];

			var displayCircleRadius = 50;
			var displayCircleOpacity = .15; 

			var selectedLogsByDay = [];
			var numLogsSelected = 0;

			var buckets = [];
			var hoursPerBucket = 24;
			

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
					streetViewControl: true,
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
						searchRefresh();
					}
					else
						updatePolyline(event.latLng);
				});

				google.maps.event.addListener(map, "mousemove", function(event) {
					refreshCircleMouseoverDisplay(event.latLng);
				});

				initializeStreetView();

				allLogsByDay = convertAllFilesContentsToLogArrays(loadAllTextFiles());

				setStatisticsDisplay(allLogsByDay.length, totalNumLogs);
				
				searchRefresh();
			}
			function initializeStreetView(){
				var closeButton = document.querySelector('#closeButton'),
        controlPosition = google.maps.ControlPosition.TOP_CENTER;

				var streetView = map.getStreetView();
				streetView.setOptions({enableCloseButton: false});

				// Add to street view
				streetView.controls[controlPosition].push(closeButton);

				streetView.setVisible(true);
				streetView.setVisible(false);

				// Listen for click event on custom button
				// Can also be $(document).on('click') if using jQuery
				google.maps.event.addDomListener(closeButton, 'click', function(){
				    streetView.setVisible(false);
				});
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
				graphCanvas.height = 145;

				graphCanvas.addEventListener('mousemove', function(event) {
	        var mousePos = getMousePos(graphCanvas, event);
	        drawGraphMouseoverDisplay(graphCanvas, mousePos);
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

				searchRefresh();
			}
			function changeStartDate(){
				var startDate = document.getElementById("startDate").value;
				var startTime = document.getElementById("startTime").value;

				var dateValues = startDate.split("-");
				var timeValues = startTime.split(":");
				
				var potStart = new Date(parseInt(dateValues[0]), parseInt(dateValues[1])-1, parseInt(dateValues[2]), parseInt(timeValues[0]), parseInt(timeValues[1]), 0, 0);
				if(isStartBeforeEnd(potStart, searchEndDate)){
					searchStartDate = potStart;
					searchRefresh();
				}
				else{
					document.getElementById("startDate").value = searchStartDate.getFullYear() + "-" + ("00" + (searchStartDate.getMonth() + 1)).slice(-2)  + "-" + ("00" + searchStartDate.getDate()).slice(-2);
					document.getElementById("startTime").value = ("00" + searchStartDate.getHours()).slice(-2) + ":" + ("00" + searchStartDate.getMinutes()).slice(-2);
				}
			}
			function changeEndDate(){
				var endDate = document.getElementById("endDate").value;
				var endTime = document.getElementById("endTime").value;

				var dateValues = endDate.split("-");
				var timeValues = endTime.split(":");

				var potEnd = new Date(parseInt(dateValues[0]), parseInt(dateValues[1])-1, parseInt(dateValues[2]), parseInt(timeValues[0]), parseInt(timeValues[1]), 0, 0);
				if(isStartBeforeEnd(searchStartDate, potEnd)){
					searchEndDate = potEnd;
					searchRefresh();
				}
				else{
					document.getElementById("endDate").value = searchEndDate.getFullYear() + "-" + ("00" + (searchEndDate.getMonth() + 1)).slice(-2)  + "-" + ("00" + searchEndDate.getDate()).slice(-2);
					document.getElementById("endTime").value = ("00" + searchEndDate.getHours()).slice(-2) + ":" + ("00" + searchEndDate.getMinutes()).slice(-2);
				}
			}
			function isStartBeforeEnd(potStart, potEnd){
				return potStart.getTime() < potEnd.getTime();
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
					strokeOpacity: .7,
					strokeWeight: 1,
					fillColor: "#0000FF",
					fillOpacity: 0,
					map: map,
					center: center,
					radius:	radius,
					editable: true,
					geodesic: true
				};
				
				searchCircle = new google.maps.Circle(circleOptions);
				setRadiusInTextBox(searchCircle.getRadius());
				setCoordinatesInTextBox(searchCircle.getCenter());
				
				google.maps.event.addListener(searchCircle, "mousemove", function(event) {
					refreshCircleMouseoverDisplay(event.latLng);
				});
				google.maps.event.addListener(searchCircle, "radius_changed", function() {
					setRadiusInTextBox(searchCircle.getRadius());
					searchRefresh();
				});
				google.maps.event.addListener(searchCircle, "center_changed", function() {
					setCoordinatesInTextBox(searchCircle.getCenter());
					searchRefresh();
				});
			}
			function updateSearchCircleBasedOnCustomRegion(){
				//TODO update fields in sidebar based on center, and radius with the distance to the farthest point

				searchRefresh();
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

				searchRefresh();
			}
			function validateAndSetRadius(){
				var radius = document.getElementById("searchRadius").value;
				var numRadius = parseInt(radius);
				
				if(isDefiningCustomRegion || isNaN(numRadius)){
					if(searchCircle == null) setRadiusInTextBox(-1);
					else setRadiusInTextBox(searchCircle.getRadius());
				}
				else {
					if(searchCircle == null)
						updateSearchCircle(map.getCenter());
					searchCircle.setRadius(numRadius);

					searchRefresh();
				}
			}
			function validateAndSetCoordinates(){
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
				
				if(isDefiningCustomRegion || searchCircle == null) setCoordinatesInTextBox(null);
				else {
					setCoordinatesInTextBox(searchCircle.getCenter());

					searchRefresh();
				}
			}
			function toggleDefineCustomRegion(){
				resetLocation();
				isDefiningCustomRegion = !isDefiningCustomRegion;
			}

			//settings
			function validateAndSetDisplayRadius(){
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
			function validateAndSetDisplayOpacity(){
				var temp =  parseFloat(document.getElementById("displayOpacity").value);
				if(isNaN(temp)){
					document.getElementById("displayOpacity").value = "";
				}
				else{
					displayCircleOpacity = temp;
					refreshListOfDisplayingLogs();
					refreshConnections();
				}
			}	
			function toggleDrawConnections(){
				isDrawingConnections = !isDrawingConnections;

				refreshConnections();
			}
			function validateAndSetGraphBucketSize(){
				var elements = document.getElementById("bucketSize").value.trim().split(" ");
				var temp =  parseInt(elements[0]);
				if(isNaN(temp)){
					var fractionParts = elements[0].trim().split("/");
					if(fractionParts.length != 2){
						hoursPerBucket = 24;
						document.getElementById("bucketSize").value = "";
					}
					else{
						//TODO they put in a fraction
					}
				}
				else{
					if(elements.length > 1){
						//TODO maybe elements[1] starts with "hr" or "hour"
					}
					hoursPerBucket = temp * 24;
				}

				refreshGraphBuckets();
				drawGraph(-1);
			}


			//general refresh
			function searchRefresh(){
				refreshListOfDisplayingLogs();
				refreshConnections();
				refreshGraphBuckets();
				drawGraph(-1);
			}

			//get logs ready as circles to draw
			function refreshListOfDisplayingLogs(){
				for(var i = 0; i < circlesToDraw.length; i++){
					circlesToDraw[i].setMap(null);
				}
				circlesToDraw = [];
				
				selectedLogsByDay = [];
				numLogsSelected = 0;

				var bounds = null;
				if(searchCircle != null) bounds = searchCircle.getBounds();
				if(searchCustomRegions.length != 0) bounds = searchCustomRegions[0].getBounds(); //TODO dependent on whether we're keeping this an array...?

				var startTime = removeTimeFromDate(searchStartDate).getTime();
				var endTime = searchEndDate.getTime();
				var isFirstDay = true;

				for(var day = 0; day < allLogsByDay.length; day++){
					var thisDaysTime = allLogsByDay[day].date.getTime();
					if(thisDaysTime < startTime) continue;
					if(thisDaysTime > endTime) break;
					
					//TODO check travel circle for the day here
					var dayLogs = [];
					for(var log = 0; log < allLogsByDay[day].logs.length; log++){
						var nextLog = allLogsByDay[day].logs[log];
						if((searchCircle == null && searchCustomRegions.length == 0) || bounds.contains(nextLog.location)){
							if(!isFirstDay || getHoursFromTimeStr(nextLog.time) > searchStartDate.getHours() || (getHoursFromTimeStr(nextLog.time) == searchStartDate.getHours() && getMinutesFromTimeStr(nextLog.time) >= searchStartDate.getMinutes())){
								isFirstDay = false;
								dayLogs.push(nextLog);
								numLogsSelected++;
							}
						}
					}

					if(dayLogs.length > 0){
						var dayData = {
							logs: dayLogs,
							date: allLogsByDay[day].date
						};
						selectedLogsByDay.push(dayData);
					}
				}

				fraction = Math.floor(numLogsSelected/MAX_CIRCLES_DISPLAYABLE) + 1;

				var counter = -1;
				for(var day = 0; day < selectedLogsByDay.length; day++){
					for(var log = 0; log < selectedLogsByDay[day].logs.length; log++){
						counter++;
						if(counter % fraction != 0) continue;

						var circleOptions = {
							strokeColor: "#FF0000",
							strokeOpacity: 0,
							strokeWeight: 1,
							fillColor: "#FF0000",
							fillOpacity: displayCircleOpacity,
							map: map,
							center: selectedLogsByDay[day].logs[log].location,
							radius:	displayCircleRadius,
							geodesic: true
						};

						var currCircle = new google.maps.Circle(circleOptions);

						google.maps.event.addListener(currCircle, "mousemove", function(event) {
							refreshCircleMouseoverDisplay(event.latLng);
						});

						circlesToDraw.push(currCircle);
					}
				}

				setStatisticsDisplay2(selectedLogsByDay.length, numLogsSelected, circlesToDraw.length);
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
					for (var day = 0; day < selectedLogsByDay.length; day++) {
						if(selectedLogsByDay[day].logs.length <= firstIndex){
							firstIndex -= selectedLogsByDay[day].logs.length;
						}
						else{
							var currTime = selectedLogsByDay[day].logs[firstIndex].time;
							var currDate = selectedLogsByDay[day].date;
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
			function refreshConnections(){
				for(var i = 0; i < connectionsToDraw.length; i++){
					connectionsToDraw[i].setMap(null);
				}
				connectionsToDraw = [];

				if(!isDrawingConnections) return;

				var polyOptions = {
			    strokeColor: '#FF0000',
			    strokeOpacity: displayCircleOpacity,
			    strokeWeight: 5
			  };

			  for(var i = 0; i < selectedLogsByDay.length; i++){
			  	for(var j = 0; j < selectedLogsByDay[i].logs.length - 1; j++){
			  		var log1 = selectedLogsByDay[i].logs[j];
			  		var log2 = selectedLogsByDay[i].logs[j+1];
				  	if(areOverlapping(log1.location, log2.location)){
				  		continue;
				  	}
					  var nextLine = new google.maps.Polyline(polyOptions);
					  nextLine.setMap(map);
					  var path = nextLine.getPath();
			  		path.push(log1.location);
			  		path.push(log2.location);
			  		connectionsToDraw.push(nextLine);
			  	}
				}
			}

			//bottom bar graph functions
			function refreshGraphBuckets(){
				buckets = [];

				var allLogsIndex = 0;
				var loopStartDate = removeTimeFromDate(searchStartDate);

				var numDays = Math.floor(((removeTimeFromDate(searchEndDate).getTime() - removeTimeFromDate(searchStartDate).getTime()) / (1000 * 60 * 60 * 24)) + .5);
				var minDaysPerBucket = Math.floor(numDays / MAX_BUCKETS);
				if(minDaysPerBucket == 0) minDaysPerBucket = 1;

 				if(hoursPerBucket < minDaysPerBucket * 24) 
				{
					hoursPerBucket = minDaysPerBucket * 24;
 					setBucketSizeInTextBox(minDaysPerBucket + " days");
 				}

				while(allLogsIndex < selectedLogsByDay.length && loopStartDate.getTime() > selectedLogsByDay[allLogsIndex].date.getTime()){
					allLogsIndex++;
				}

				var counter = 0;
				var bucket = {
					startDate: null,
					endDate: null,
					logIndices: []
				};

				if(hoursPerBucket < 24){
					//TODO implement
				}
				var daysPerBucket = hoursPerBucket / 24;

				for(var year = loopStartDate.getFullYear(); year <= searchEndDate.getFullYear(); year++){
					for(var month = 0; month < 12; month++){
						if(year == loopStartDate.getFullYear() && month < loopStartDate.getMonth()) continue;
						if(year == searchEndDate.getFullYear() && month > searchEndDate.getMonth()) break;
						for(var dom = 1; dom < 32; dom++){
							if(year == loopStartDate.getFullYear() && month == loopStartDate.getMonth() && dom < loopStartDate.getDate()) continue;
							if(year == searchEndDate.getFullYear() && month == searchEndDate.getMonth() && dom > searchEndDate.getDate()) break;

							var currDate = makeCleanDate(year, month, dom);
							if(dom != currDate.getDate()) {
								continue;
							}

							if(counter % daysPerBucket == 0) {
								bucket = {
									startDate: currDate,
									logIndices: []
								};
							}
							
							if(allLogsIndex < selectedLogsByDay.length && areDatesEqual(currDate, selectedLogsByDay[allLogsIndex].date)){
								bucket.logIndices.push(allLogsIndex);
								allLogsIndex++;
							}

							if((counter + 1) % daysPerBucket == 0) {
								bucket.endDate = currDate;
								buckets.push(bucket);
							}
							counter++;
						}
					}
				}

				if(!areDatesEqual(buckets[buckets.length-1].startDate, bucket.startDate)){
					bucket.endDate = removeTimeFromDate(searchEndDate);
					buckets.push(bucket);
				}
			}
			function drawGraph(bucketSelected){
				var totNumBuckets = buckets.length;
				var totLength = 1320;
				var maxHeight = 100;

				var maxNumLogs = 1;
				for(var i = 0; i < totNumBuckets; i++){
					var potMax = getNumLogsFromLogIndices(buckets[i].logIndices);
					if(potMax > maxNumLogs) maxNumLogs = potMax;
				}

				var canvas = (document.getElementById("bottomCanvas"));
				canvas.width = canvas.width;

				var context = canvas.getContext("2d");
				context.fillRect(32, 125, totLength + 20, 3); //should be 1320 long from (42, 175) to (1362, 175)

				if(bucketSelected == -1) context.fillStyle = "#444444";
				else context.fillStyle = "#666666";

				for(var i = 0; i < totNumBuckets; i++){
					if(bucketSelected == i) context.fillStyle = "#222222";
					var currNumLogs = getNumLogsFromLogIndices(buckets[i].logIndices);
					drawIndividualBucket(context, i, (currNumLogs / maxNumLogs) * maxHeight, totNumBuckets, totLength);
					if(bucketSelected == i) context.fillStyle = "#666666"; //POTENTIAL could draw red box here to show bucket when number is 0
				}
			}
			function getNumLogsFromLogIndices(logIndices){
				var sum = 0;
				for(var i = 0; i < logIndices.length; i++){
					sum += selectedLogsByDay[logIndices[i]].logs.length;
				}
				return sum;
			}
			function drawGraphMouseoverDisplay(graphCanvas, mousePos){
				graphCanvas.width = graphCanvas.width;

				var bucketSelected = -1;
				if(mousePos.x >= 42 && mousePos.x < 1362 && mousePos.y >= 25 && mousePos.y < 125){
					bucketSelected = Math.floor(((mousePos.x - 42) / 1320) * buckets.length);
				}

				drawGraph(bucketSelected);
				if(bucketSelected != -1){
					var info = dateToShortStr(buckets[bucketSelected].startDate);
					if(hoursPerBucket != 24) info += " - " + dateToShortStr(buckets[bucketSelected].endDate);
					info += "  ::  " + getNumLogsFromLogIndices(buckets[bucketSelected].logIndices);
					info += " logs";
					setStatisticsDisplay3(info);
				}
				else setStatisticsDisplay3("");
			}
			function drawIndividualBucket(context, bucket, height, totNumBuckets, totLength){
				//(totLength / totNumBuckets) should be an integer. totLength is like 1320, not 1340 - no padding
				context.fillRect(42 + (totLength / totNumBuckets) * bucket, 125 - height, (totLength / totNumBuckets) + 1, height);
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
			function setBucketSizeInTextBox(bucketSize){
				if(bucketSize == null)
					document.getElementById("bucketSize").value = "";
				else
					document.getElementById("bucketSize").value = bucketSize;
			}
			function setStatisticsDisplay(numDays, numLogs){
				document.getElementById("currentStats").innerHTML = "Loaded a total of " + numLogs + " logs from " + numDays + " days";
			}
			function setStatisticsDisplay2(numDays, numLogsSelected, numLogsDisplaying){ 
				if(numLogsDisplaying == numLogsSelected)
					document.getElementById("currentStats2").innerHTML = "Displaying " + numLogsDisplaying + " logs from " + numDays + " days";
				else
					document.getElementById("currentStats2").innerHTML = "Displaying " + numLogsDisplaying + " of " + numLogsSelected + " logs from " + numDays + " days";
			}
			function setStatisticsDisplay3(message){
				document.getElementById("currentStats3").innerHTML = message;
			}


			//comparisons
			function areDatesEqual(date1, date2){
				return ((date1.getFullYear() == date2.getFullYear()) && (date1.getMonth() == date2.getMonth()) && (date1.getDate() == date2.getDate()));
			}
			function areOverlapping(loc1, loc2){
				var circleOptions = {
					center: loc1,
					radius:	displayCircleRadius * 2
				};

				var circ = new google.maps.Circle(circleOptions);
				return circ.getBounds().contains(loc2);
			}


			//time conversions
			function makeCleanDate(year, month, day){
				return new Date(year, month, day, 0, 0, 0, 0);
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
			function addTimeStrToDate(existingDate, timeStr){
				var segments = timeStr.trim().split(":");
				existingDate.setHours(parseInt(segments[0]));
				existingDate.setMinutes(parseInt(segments[1]));
				existingDate.setSeconds(parseInt(segments[2]));
				return existingDate;
			}
			function removeTimeFromDate(existingDate){
				var newDate = new Date(existingDate.getFullYear(), existingDate.getMonth(), existingDate.getDate(), 0, 0, 0, 0)
				return newDate;
			}
			function getHoursFromTimeStr(timeStr){
				return parseInt(timeStr.split(":")[0].trim());
			}
			function getMinutesFromTimeStr(timeStr){
				return parseInt(timeStr.split(":")[1].trim());
			}
			function dateToShortStr(existingDate){
				var result = "";
				result += (existingDate.getMonth() + 1) + "/";
				result += existingDate.getDate() + "/";
				result += existingDate.getFullYear();
				return result;
			}

			//location conversions
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
					<input type="text" id="searchCoordinates" class="controls" onchange="validateAndSetCoordinates()" placeholder="Coordinates" required="required">
					<input type="text" id="searchRadius" class="controls" onchange="validateAndSetRadius()" placeholder="Radius (ft)" required="required">
					<input type="checkbox" id="connections" onchange="toggleDefineCustomRegion()">Define custom region</input>
				</div>
				<div>
					<br>
					<h1>Settings</h1>
					<input type="text" id="displayRadius" class="controls" onchange="validateAndSetDisplayRadius()" placeholder="Display Radius (default 50 ft)">
					<input type="text" id="displayOpacity" class="controls" onchange="validateAndSetDisplayOpacity()" placeholder="Display Opacity (default .15)">
					<input type="text" id="bucketSize" class="controls" onchange="validateAndSetGraphBucketSize()" placeholder="Graph Bucket Size (default 1 day)">
					<input type="checkbox" id="connections" onchange="toggleDrawConnections()">Draw connections</input>
				</div>
				<div>
					<br><br><br>
					<p id="currentStats"><p>
					<p id="currentStats2"><p>
					<p id="currentStats3"><p>
    			<button type="button" id="closeButton">Close Street View</button>
				</div>
			</form>
		</div>
		<div id="bottomBar">
			<canvas id="bottomCanvas"></canvas>
		</div>
		
		<script>
			initializeSidebar();
			initializeBottomBar();
		</script>
  </body>
</html>