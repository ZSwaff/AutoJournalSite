<!DOCTYPE html>
<html>
  <head>
  	<link rel="shortcut icon" href="http://localhost/AutoJournal/res/icon.ico">
    <title>AutoJournal</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no">
    <meta charset="utf-8">
    <style type="text/css">
      html, body, #mapCanvas {
        height: 100%;
        min-width: 1280px;
    		min-height: 800px;
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
				margin-right: 16px;
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
				box-shadow: 0px -3px 25px 5px rgba(0, 0, 0, 0.2);
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

      #latLngDisp {
      	font-size: 1em;
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
		<script type="text/javascript"
      src="https://maps.googleapis.com/maps/api/js?v=3.exp&libraries=places"> //"https://maps.googleapis.com/maps/api/js?key=AIzaSyDuzuNuG6mRj6N9f3GJWMg7EP3ZKHAdfFA">
    </script>
    <script>
    	//look for CURRENT, TODO, POTENTIAL, and NOTE tags

    	var MONTH_REF_NO_NUMS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
			var STANFORD_LOCATION = new google.maps.LatLng(37.424188, -122.166349);

			var MAX_BUCKETS = 660;
			var MAX_CIRCLES_DISPLAYABLE = 1000;


			var windowWidth = window.innerWidth;
			var windowHeight = window.innerHeight;
			var bottomBarWidth;
			
			var map;

			var allLogsByDay = []; //this array stores day objects, which have "date", "numErrors", and "logs" properties. "logs" is an array of log objects that have "times"s and "location"s
			var totalNumLogs = 0;
			var totalMissingLogs = 0; //NOTE does not include days missed entirely, just the missing logs from days partially missed

			var searchCircle = null;
			var isDefiningCustomRegion = false;
			var searchPolyline = null;
			var closeRegionMarker = null;
			var searchCustomRegions = [];

			var searchStartDate = null;
			var searchEndDate = null;

			var denominatorOfFractionOfCirclesDisplaying = 1;
			var circlesToDraw = [];
			var isDrawingConnections = false;
			var connectionsToDraw = [];

			var displayCircleRadius = 30;
			var displayCircleOpacity = .15; 

			var selectedLogsByDay = [];
			var numLogsSelected = 0;

			var histogramBuckets = [];
			var hoursPerBucket = 24;
			var displayGraphErrorCorrection = true;
			

			function resize(){
				windowWidth = window.innerWidth;
				windowHeight = window.innerHeight;
				
				//CURRENT
				//TODO move sidebar
				//resize bottom bar, graph
				//set this method up to be called at start, too
			}
			function getMousePos(canvas, event) {
				//gets the mouse's position
        var rect = canvas.getBoundingClientRect();
        var result = {
					x: event.clientX - rect.left,
          y: event.clientY - rect.top
        };
        return result;
      }


			//init display
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
					else{
						updatePolyline(event.latLng);
					}
				});

				//update displays when the mouse moves over something
				google.maps.event.addListener(map, "mousemove", function(event) {
					setLatLngDisp(event.latLng);
					refreshCircleMouseoverDisplay(event.latLng);
				});

				initializeStreetView();
				initializeLatLngDisp();

				//load all the logs
				convertAllFilesContentsToLogArrays(loadAllTextFiles());

				setTotalStatsDisplay(allLogsByDay.length, totalNumLogs);
				
				searchRefresh();
			}
			function initializeStreetView(){
				//set up street view and its custom close button
				var closeButton = document.querySelector('#closeButton'),
        controlPosition = google.maps.ControlPosition.TOP_CENTER;

				var streetView = map.getStreetView();
				streetView.setOptions({enableCloseButton: false});

				streetView.controls[controlPosition].push(closeButton);

				streetView.setVisible(true);
				streetView.setVisible(false);

				//listen for click event on custom button
				google.maps.event.addDomListener(closeButton, 'click', function(){
				    streetView.setVisible(false);
				});
			}
			function initializeLatLngDisp(){
				//pushes the small lat / lng display to the top of the map
				var latLngDisp = document.querySelector('#latLngDisp'),
        controlPosition = google.maps.ControlPosition.TOP_CENTER;

				map.controls[controlPosition].push(latLngDisp);
			}
			function initializeSidebar(){
				//sets up the sidebar by setting the time and allowing mouseovers
				resetTime();

				document.getElementById("sidebar").addEventListener('mousemove', function(event) {
	        setMouseoverMessageDisplay("");
	        setLatLngDisp(null);
      	}, false);
			}
			function initializeBottomBar(){
				//sets up the bottom bar by setting its size and allowing mouseovers
				var bottomBarWidth = (1680 - 270);
				document.getElementById("bottomBar").style.width = bottomBarWidth + "px";

				var graphCanvas = document.getElementById("bottomCanvas");
				graphCanvas.width = bottomBarWidth - 5;
				graphCanvas.height = 145;

				graphCanvas.addEventListener('mousemove', function(event) {
	        var mousePos = getMousePos(graphCanvas, event);
	        drawGraphMouseoverDisplay(graphCanvas, mousePos);
	        setLatLngDisp(null);
      	}, false);
			}
			

			//load and deal with actual files
			function loadAllTextFiles(){
				//uses php to loop through all the stored text files
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

								$phpFileContents = file_get_contents("Location Logs/{$year}/{$monthStr}/{$domStr}.txt");
								$phpAllFileContents[] = $phpFileContents;
							}
						}
					}
				?>

				//expands the files as stored in php and stores them in js variables
				var fileContentsCompressed = <?php echo json_encode($phpAllFileContents); ?>;
				var fileContents = [];
				for(var i = 0; i < fileContentsCompressed.length; i++){
					fileContents.push(fileContentsCompressed[i].split("\n"));
				}
				
				return fileContents;
			}
			function convertAllFilesContentsToLogArrays(allFileContents){
				//converts the raw (expanded but unprocessed) data to processed, filtered shit as described in the initilization above
				allLogsByDay = [];
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
						numErrors: 288 - dayLogs.length,
						date: stringToDate(allFileContents[day][0])
					};
					allLogsByDay.push(dayData);

					totalMissingLogs += dayData.numErrors;
				}
				
				return allLogsByDay;
			}
			

			//validate and use new input
			//time
			function resetTime(){
				//sets the time to defaults - the time of the first log through the current time
				searchStartDate = new Date(2013, 5, 3, 19, 30, 0, 0);

				document.getElementById("startDate").value = searchStartDate.getFullYear() + "-" + ("00" + (searchStartDate.getMonth() + 1)).slice(-2)  + "-" + ("00" + searchStartDate.getDate()).slice(-2);
				document.getElementById("startTime").value = ("00" + searchStartDate.getHours()).slice(-2) + ":" + ("00" + searchStartDate.getMinutes()).slice(-2);

				searchEndDate = new Date();
				searchEndDate.setMinutes(Math.floor(searchEndDate.getMinutes() / 5) * 5);

				document.getElementById("endDate").value = searchEndDate.getFullYear() + "-" + ("00" + (searchEndDate.getMonth() + 1)).slice(-2)  + "-" + ("00" + searchEndDate.getDate()).slice(-2);
				document.getElementById("endTime").value = ("00" + searchEndDate.getHours()).slice(-2) + ":" + ("00" + searchEndDate.getMinutes()).slice(-2);

				searchRefresh();
			}
			function startDateChanged(){
				//if the date is changed, set the time so it includes all of that day and then update the date
				document.getElementById("startTime").value = "00:00";
				validateAndSetSearchStartDate();
			}
			function endDateChanged(){
				//if the date is changed, set the time so it includes all of that day and then update the date
				document.getElementById("endTime").value = "23:59";
				validateAndSetSearchEndDate();
			}
			function validateAndSetSearchStartDate(){
				//makes sure that the start date is before the end date, then updates based on the new date
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
			function validateAndSetSearchEndDate(){
				//makes sure that the start date is before the end date, then updates based on the new date
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

			//location
			function updateSearchCircle(center){
				//creates/updates the search circle based on the new location
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
					fillOpacity: 0.05,
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
					setLatLngDisp(event.latLng);
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
			function updatePolyline(clickLoc){
				//if the user is defining a custom region, this handles the clicks
				if(closeRegionMarker != null && closeRegionMarker.getMap() == null){
					//the region is intended to be closed, so close it and don't mess around
			  	closeRegionMarker = null;
			  } 
			  else {
			  	//add a new point to the path, and put the marker down if it's the first click of the region
					var path = searchPolyline.getPath();
				  path.push(clickLoc);

				  if(closeRegionMarker == null){
					  closeRegionMarker = new google.maps.Marker({
					    position: clickLoc,
					    title: 'Click to close the region',
					    icon: {
					      path: google.maps.SymbolPath.CIRCLE,
					      scale: 3
					    },
					    draggable: false,
					    map: map
					  });

						google.maps.event.addListener(closeRegionMarker, 'click', function(event) {
							closeCustomRegion();
						});
				  }
				}
			}
			function closeCustomRegion(){
				//triggered when the start marker is clicked again, signalling the end of the region
				var path = searchPolyline.getPath();
				if(path.length <= 2) return; //the path is still to short; there is no region really

				closeRegionMarker.setMap(null);

				//reset the polyline
				searchPolyline.setMap(null);
				var polyOptions = {
			    strokeColor: '#0000FF',
			    strokeOpacity: 1,
			    strokeWeight: 1
			  };
			  searchPolyline = new google.maps.Polyline(polyOptions);
			  searchPolyline.setMap(map);

			  //make the search polygon
				var polygonOptions = {
			    map: map,
			    paths: path,
			    strokeColor: '#0000FF',
			    strokeOpacity: .7,
			    strokeWeight: 1,
			    fillColor: '#0000FF',
			    fillOpacity: 0.05,
			    draggable: true,
			    geodesic: true
			  };

				var searchCustomRegion = new google.maps.Polygon(polygonOptions);

				google.maps.event.addListener(searchCustomRegion, 'dragend', function(event) {
					searchRefresh();
				});
				google.maps.event.addListener(searchCustomRegion, "mousemove", function(event) {
					setLatLngDisp(event.latLng);
					refreshCircleMouseoverDisplay(event.latLng);
				});

				searchCustomRegions.push(searchCustomRegion);

				searchRefresh();
			}

			function resetLocation(){
				//clear all the boxes, reset all the location-based search criteria
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

			  closeRegionMarker = null;

				for(var i = 0; i < searchCustomRegions.length; i++){
					searchCustomRegions[i].setMap(null);
				}
				searchCustomRegions = [];

				searchRefresh();
			}
			function validateAndSetRadius(){
				//make sure the radius is valid, if it is, set the circle to it
				var radius = document.getElementById("searchRadius").value;
				var numRadius = parseInt(radius);
				
				if(isDefiningCustomRegion || isNaN(numRadius)){
					if(searchCircle == null) {
						setRadiusInTextBox(-1);
					}
					else {
						setRadiusInTextBox(searchCircle.getRadius());
					}
				}
				else {
					if(searchCircle == null){
						updateSearchCircle(map.getCenter());
					}
					searchCircle.setRadius(numRadius);

					searchRefresh();
				}
			}
			function validateAndSetCoordinates(){
				//make sure the coordinates are valid, if thet are, set the circle to them
				var center = document.getElementById("searchCoordinates").value.trim();
				var coords = center.split(" ");
				
				if(coords.length == 2) {
					var lat = parseFloat(coords[0]);
					var lng = parseFloat(coords[1]);
					
					if(!isNaN(lat) && !isNaN(lng)) {
						var loc = new google.maps.LatLng(lat, lng);
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
				//make sure the display radius is valid, if it is, set the circles to it
				if(document.getElementById("displayRadius").value == ""){
					displayCircleRadius = 30;
					for(var i = 0; i < circlesToDraw.length; i++){
						circlesToDraw[i].setRadius(displayCircleRadius);
					}
				}
				var temp = parseInt(document.getElementById("displayRadius").value);
				if(isNaN(temp)){
					document.getElementById("displayRadius").value = "";
				}
				else{
					displayCircleRadius = temp;
					for(var i = 0; i < circlesToDraw.length; i++){
						circlesToDraw[i].setRadius(displayCircleRadius);
					}
				}
			}
			function validateAndSetDisplayOpacity(){
				//make sure the display opacity is valid, if it is, set the circles to it
				if(document.getElementById("displayOpacity").value == ""){
					displayCircleOpacity = .15;
					refreshCirclesToDraw();
					refreshConnections();
				}
				var temp =  parseFloat(document.getElementById("displayOpacity").value);
				if(isNaN(temp)){
					document.getElementById("displayOpacity").value = "";
				}
				else{
					displayCircleOpacity = temp;
					refreshCirclesToDraw();
					refreshConnections();
				}
			}	
			function toggleDrawConnections(){
				isDrawingConnections = !isDrawingConnections;
				refreshConnections();
			}
			function validateAndSetGraphBucketSize(){
				//make sure the bucket size is valid, if it is, set that up
				var temp =  parseInt(document.getElementById("bucketSize").value.trim());
				if(document.getElementById("bucketSize").value == "" || isNaN(temp)){
					hoursPerBucket = 24;
					document.getElementById("bucketSize").value = "";
				}
				else{
					hoursPerBucket = temp * 24;
				}

				refreshGraphBuckets();
				drawGraph(-1);
			}


			//general refresh
			function searchRefresh(){
				//reset and refresh basically all of the displays
				refreshListOfDisplayingLogs();
				refreshCirclesToDraw();
				refreshConnections();
				refreshGraphBuckets();
				drawGraph(-1);
			}

			//refresh and redraw map displays
			function refreshListOfDisplayingLogs(){
				//refreshes selectedLogsByDay based on time and location
				selectedLogsByDay = [];
				numLogsSelected = 0;

				//create an array of square regions encompassing the smaller, irregular shapes to narrow it down
				var bounds = [];
				if(searchCircle != null) {
					bounds.push(searchCircle.getBounds());
				}
				if(searchCustomRegions.length != 0) {
					if(bounds.length != 0) {
						alert("both a circle and at least one custom region are defined!");
					}
					for(var i = 0; i < searchCustomRegions.length; i++){
						bounds.push(makeBounds(searchCustomRegions[i]));
					}
				}

				//set up the timing
				var startTime = removeTimeFromDate(searchStartDate).getTime();
				var endTime = searchEndDate.getTime();
				var isFirstDay = true;

				for(var day = 0; day < allLogsByDay.length; day++){
					//if the time is bad, don't even look
					var thisDaysTime = allLogsByDay[day].date.getTime();
					if(thisDaysTime < startTime) continue;
					if(thisDaysTime > endTime) break;
					
					//TODO check travel circle for the day here
					var dayLogs = [];
					for(var log = 0; log < allLogsByDay[day].logs.length; log++){
						var nextLog = allLogsByDay[day].logs[log];
						var isInARegion = false;

						//if there are no bounds, we're including everything. otherwise, check to see if it's in a region
						if(bounds.length != 0){
							var squareRegionsThatContain = [];
							for(var i = 0; i < bounds.length; i++){
								if (bounds[i].contains(nextLog.location)){
									squareRegionsThatContain.push(i);
								}
							}

							//if none of the squares have the location in them, give up
							if(squareRegionsThatContain.length != 0){
								//otherwise, check either the circle or the regions that might work more thoroughly
								if(searchCircle != null){
									isInARegion = doesCircleContain(searchCircle, nextLog.location);
								}
								else{
									for(var i = 0; i < squareRegionsThatContain.length; i++){
										var nwPoint = bounds[squareRegionsThatContain[i]].getNorthEast();
										var pointOutside = new google.maps.LatLng(nwPoint.lat() + .0001, nwPoint.lng() + .0001);

										isInARegion = doesRegionContain(searchCustomRegions[squareRegionsThatContain[i]], nextLog.location, pointOutside);
										if(isInARegion) break;
									}
								}
							}
						}

						//if the time works, check on the location
						if(!isFirstDay || getHoursFromTimeStr(nextLog.time) > searchStartDate.getHours() || (getHoursFromTimeStr(nextLog.time) == searchStartDate.getHours() && getMinutesFromTimeStr(nextLog.time) >= searchStartDate.getMinutes())){
							isFirstDay = false;
							//if there are no bounds, or this is in the bounds, add it to the ones currently selected
							if(bounds.length == 0 || isInARegion){
								dayLogs.push(nextLog);
								numLogsSelected++;
							}
						}
					}

					//if we got more than one log for the day, make a log for the day and add it to selectedLogsByDay
					if(dayLogs.length > 0){
						var dayData = {
							logs: dayLogs,
							numErrors: allLogsByDay[day].numErrors,
							date: allLogsByDay[day].date
						};
						selectedLogsByDay.push(dayData);
					}
				}
			}
			function refreshCirclesToDraw(){
				//reset the old circles
				for(var i = 0; i < circlesToDraw.length; i++){
					circlesToDraw[i].setMap(null);
				}
				circlesToDraw = [];

				//calculate how often we're going to draw the circle we have
				denominatorOfFractionOfCirclesDisplaying = Math.floor(numLogsSelected/MAX_CIRCLES_DISPLAYABLE) + 1;
				//POTENTIAL add a fraction to put invisible circles on the map

				var counter = -1;
				for(var day = 0; day < selectedLogsByDay.length; day++){
					for(var log = 0; log < selectedLogsByDay[day].logs.length; log++){
						//a very straightforward way of skipping a high portion of the logs
						counter++;
						if(counter % denominatorOfFractionOfCirclesDisplaying != 0) continue;

						//make a new circle
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

						//register some listeners so it behaves basically just like the map
						google.maps.event.addListener(currCircle, "mousemove", function(event) {
							setLatLngDisp(event.latLng);
							refreshCircleMouseoverDisplay(event.latLng);
						});

						google.maps.event.addListener(currCircle, "click", function(event) {
							if(!isDefiningCustomRegion){
								updateSearchCircle(event.latLng);
								searchRefresh();
							}
							else{
								updatePolyline(event.latLng);
							}
						});

						//add the circle to the list
						circlesToDraw.push(currCircle);
					}
				}

				setCurrentlyDisplayingStatisticsDisplay(selectedLogsByDay.length, numLogsSelected, circlesToDraw.length);
			}
			function refreshCircleMouseoverDisplay(mouseLoc){
				//updates the current mouseover display with information about the log(s) that the mouse is over
				var indicesOfNearbyCircles = [];
				//make a list of the indices (in the circle list) of the circles that contain the mouse's location
				for(var index = 0; index < circlesToDraw.length; index++)
				{
					if(circlesToDraw[index].getBounds().contains(mouseLoc)){
						indicesOfNearbyCircles.push(index);
					}
				}

				if(indicesOfNearbyCircles.length == 0){
				//if there are no logs nearby, don't display anything
					setMouseoverMessageDisplay("");
				}
				else if(indicesOfNearbyCircles.length == 1){
					//if there is just one log, give all of its information
					var firstIndex = indicesOfNearbyCircles[0] * denominatorOfFractionOfCirclesDisplaying;

					for (var day = 0; day < selectedLogsByDay.length; day++) {
						if(selectedLogsByDay[day].logs.length <= firstIndex){
							firstIndex -= selectedLogsByDay[day].logs.length;
						}
						else{
							var currTime = selectedLogsByDay[day].logs[firstIndex].time;
							var currDate = selectedLogsByDay[day].date;

							currDate = addTimeStrToDate(currDate, currTime);
							setMouseoverMessageDisplay("This log is from " + currDate.toLocaleTimeString() + " on " + currDate.toLocaleDateString());
							break;
						}
					}
				}
				else {
					//if there are many logs, we're going to have to condense to fit them in that little area
					var info = "There are " + indicesOfNearbyCircles.length + " logs here from ";

					//create a set of all the dates that these circles are from
					var dateSet = [];
					var cumuLogTotal = 0;
					var day = 0;
					for(var i = 0; i < indicesOfNearbyCircles.length; i++){
						var nextIndex = indicesOfNearbyCircles[i] * denominatorOfFractionOfCirclesDisplaying;
						for ( ; day < selectedLogsByDay.length; day++) {
							if(selectedLogsByDay[day].logs.length <= nextIndex - cumuLogTotal){
								cumuLogTotal += selectedLogsByDay[day].logs.length;
							}
							else{
								if(dateSet.length == 0 || !areDatesEqual(dateSet[dateSet.length-1], selectedLogsByDay[day].date)){
									dateSet.push(selectedLogsByDay[day].date);
								}
								break;
							}
						}
					}
					
					//variables to keep track of where we are in this complicated date string creating
					var monthCount = 0;
					var firstDateOfMonth = dateSet[0];
					var monthInfo = dateSet[0].getDate() + "";
					var lastDay = dateSet[0].getDate();

					//go through the days and, if it's a new month, do a whole reset. otherwise, make a range if there are several days in a row. otherwise just add the date
					for(var i = 1; i < dateSet.length; i++){
						if(firstDateOfMonth.getMonth() == dateSet[i].getMonth() && firstDateOfMonth.getFullYear() == dateSet[i].getFullYear()){
							//if we're still in the same month
							var nextDay = dateSet[i].getDate();
							if(nextDay != lastDay + 1){
								//if we're not part of a range
								if(firstDateOfMonth.getDate() != lastDay){
									//if we were, up until now, part of a range
									monthInfo += "-"+lastDay;
								}
								monthInfo += ", " + nextDay;
								firstDateOfMonth = dateSet[i];
							}
							lastDay = nextDay;
						}
						else{
							//if we're in a new month

							if(firstDateOfMonth.getDate() != lastDay){
								//if we were, up until now, part of a range
								monthInfo += "-"+lastDay;
							}

							monthCount++;
							if(monthInfo.length > 2) {
								monthInfo = "(" + monthInfo + ")";
							}
							info += (firstDateOfMonth.getMonth() + 1) + "-" + monthInfo + "-" + firstDateOfMonth.getFullYear() + ", ";
							firstDateOfMonth = dateSet[i];
							monthInfo = dateSet[i].getDate() + "";
							lastDay = dateSet[i].getDate();
						}
					}

					if(firstDateOfMonth.getDate() != lastDay){
						//if we were, up until now, part of a range
						monthInfo += "-"+lastDay;
					}

					if(monthCount == 1) {
						info = info.substring(0, info.length - 2) + " ";
					}
					if(monthCount != 0) {
						info += "and "
					}
					if(monthInfo.length > 2) {
						monthInfo = "(" + monthInfo + ")";
					}
					info += (firstDateOfMonth.getMonth() + 1) + "-" + monthInfo + "-" + firstDateOfMonth.getFullYear();

					setMouseoverMessageDisplay(info);
				}
			}
			function refreshConnections(){
				//draw all the connections between all the logs
				for(var i = 0; i < connectionsToDraw.length; i++){
					connectionsToDraw[i].setMap(null);
				}
				connectionsToDraw = [];

				if(!isDrawingConnections) return;

				//the options for all the polylines
				var polyOptions = {
			    strokeColor: '#FF0000',
			    strokeOpacity: displayCircleOpacity,
			    strokeWeight: 5
			  };

			  //go through literally every day and connect the logs if the two logs' respective circles don't already overlap
			  for(var i = 0; i < selectedLogsByDay.length; i++){
			  	for(var j = 0; j < selectedLogsByDay[i].logs.length - 1; j++){
			  		var log1 = selectedLogsByDay[i].logs[j];
			  		var log2 = selectedLogsByDay[i].logs[j+1];
			  		//TODO make time work?
				  	if(areOverlapping(log1.location, log2.location)) continue;

					  var nextLine = new google.maps.Polyline(polyOptions);
					  nextLine.setMap(map);
					  var path = nextLine.getPath();

					  //add the locations to the path and add the path to the list of paths
			  		path.push(log1.location);
			  		path.push(log2.location);
			  		connectionsToDraw.push(nextLine);
			  	}
				}
			}

			//bottom bar graph functions
			function refreshGraphBuckets(){
				//create all the buckets to be drawn in the graph (works with the selected logs, but also adds the days that aren't there and formats for graphing)
				histogramBuckets = [];

				var allLogsIndex = 0;
				var loopStartDate = removeTimeFromDate(searchStartDate);

				//calculate the total number of days, and thus minimum size of a bucket to make the graph look ok
				var numDays = Math.floor(((removeTimeFromDate(searchEndDate).getTime() - removeTimeFromDate(searchStartDate).getTime()) / (1000 * 60 * 60 * 24)) + .5);
				var minDaysPerBucket = Math.floor(numDays / MAX_BUCKETS) + 1;

 				if(hoursPerBucket < minDaysPerBucket * 24) 
				{
					hoursPerBucket = minDaysPerBucket * 24;
 					setBucketSizeInTextBox(minDaysPerBucket + " days");
 				}

 				//bring the index here that we use match buckets to day logs up so that they match at the start
				while(allLogsIndex < selectedLogsByDay.length && loopStartDate.getTime() > selectedLogsByDay[allLogsIndex].date.getTime()){
					allLogsIndex++;
				}

				//the bucket template we will use for all the buckets
				var counter = 0;
				var bucket = {
					startDate: null,
					endDate: null,
					logIndices: []
				};

				var daysPerBucket = hoursPerBucket / 24;

				//go through each actual day that there is (not from the logs, from the real calendar)
				for(var year = loopStartDate.getFullYear(); year <= searchEndDate.getFullYear(); year++){
					for(var month = 0; month < 12; month++){
						if(year == loopStartDate.getFullYear() && month < loopStartDate.getMonth()) continue;
						if(year == searchEndDate.getFullYear() && month > searchEndDate.getMonth()) break;

						for(var dom = 1; dom < 32; dom++){
							if(year == loopStartDate.getFullYear() && month == loopStartDate.getMonth() && dom < loopStartDate.getDate()) continue;
							if(year == searchEndDate.getFullYear() && month == searchEndDate.getMonth() && dom > searchEndDate.getDate()) break;

							//if this day doesn't actually exist, that's a problem
							var currDate = makeCleanDate(year, month, dom);
							if(dom != currDate.getDate()) continue;

							//it's a new bucket
							if(counter % daysPerBucket == 0) {
								bucket = {
									startDate: currDate,
									logIndices: []
								};
							}
							
							//add this day to the current bucket's list of days
							if(allLogsIndex < selectedLogsByDay.length && areDatesEqual(currDate, selectedLogsByDay[allLogsIndex].date)){
								bucket.logIndices.push(allLogsIndex);
								allLogsIndex++;
							}

							//if this is the last day of the bucket, add it to the bucket list
							if((counter + 1) % daysPerBucket == 0) {
								bucket.endDate = currDate;
								histogramBuckets.push(bucket);
							}
							counter++;
						}
					}
				}

				//check if the last bucket should be added, and add it if so
				if(!areDatesEqual(histogramBuckets[histogramBuckets.length-1].startDate, bucket.startDate)){
					bucket.endDate = removeTimeFromDate(searchEndDate);
					histogramBuckets.push(bucket);
				}
			}
			function drawGraph(bucketSelected){
				//actually draws the graph, based on the updated buckets
				var totNumBuckets = histogramBuckets.length;
				var totLength = 1320;
				var maxHeight = 100;

				var maxNumLogs = 1;
				var totalLogsInPeriod = 0;

				//find the maximum number of logs in any day, so that the graph can be normalized to that height
				for(var i = 0; i < totNumBuckets; i++){
					var potMax = getNumLogsFromLogIndices(histogramBuckets[i].logIndices);
					if(potMax > maxNumLogs) {
						maxNumLogs = potMax;
					}

					totalLogsInPeriod += potMax;
				}

				//set up the canvas
				var canvas = (document.getElementById("bottomCanvas"));
				canvas.width = canvas.width;

				var context = canvas.getContext("2d");
				context.fillRect(32, 125, totLength + 20, 3); //should be 1320 long from (42, 175) to (1362, 175)

				if(bucketSelected == -1) {
					context.fillStyle = "#444444";
				}
				else {
					context.fillStyle = "#666666";
				}

				//draw the buckets
				for(var i = 0; i < totNumBuckets; i++){
					if(bucketSelected == i) {
						context.fillStyle = "#222222";
					}

					var currNumLogs = getNumLogsFromLogIndices(histogramBuckets[i].logIndices);
					drawIndividualBucket(context, i, (currNumLogs / maxNumLogs) * maxHeight, totNumBuckets, totLength);

					if(bucketSelected == i) {
						context.fillStyle = "#666666";
					}
				}
			}
			function getNumLogsFromLogIndices(logIndices){
				//sums all the logs for a given bucket's list of day indices
				var sum = 0;
				for(var i = 0; i < logIndices.length; i++){
					sum += selectedLogsByDay[logIndices[i]].logs.length;
				}
				return sum;
			}
			function drawGraphMouseoverDisplay(graphCanvas, mousePos){
				//wrapper for the drawGraph function that makes the day you're mousing over bolder
				graphCanvas.width = graphCanvas.width;

				//calculate the bucket, draw
				var bucketSelected = -1;
				if(mousePos.x >= 42 && mousePos.x < 1362 && mousePos.y >= 25 && mousePos.y < 125){
					bucketSelected = Math.floor(((mousePos.x - 42) / 1320) * histogramBuckets.length);
				}
				drawGraph(bucketSelected);

				//display the moused-over bucket's info in the message display field
				if(bucketSelected != -1){
					var info = dateToShortStr(histogramBuckets[bucketSelected].startDate);
					if(hoursPerBucket != 24) {
						info += " - " + dateToShortStr(histogramBuckets[bucketSelected].endDate);
					}
					info += "  ::  " + getNumLogsFromLogIndices(histogramBuckets[bucketSelected].logIndices) + " logs";
					setMouseoverMessageDisplay(info);
				}
				else {
					setMouseoverMessageDisplay("");
				}
			}
			function drawIndividualBucket(context, bucket, height, totNumBuckets, totLength){
				//(totLength / totNumBuckets) should be an integer. totLength is like 1320, not 1340 - no padding
				context.fillRect(42 + (totLength / totNumBuckets) * bucket, 125 - height, (totLength / totNumBuckets) + 1, height);
			}


			//set values in sidebar based on inside changes
			function setRadiusInTextBox(radius){
				//if the radius is determined internally, set the value shown in the textbox. -1 for blank
				if(radius < 0) {
					document.getElementById("searchRadius").value = "";
				}
				else {
					radius = Math.floor(radius + .5);
					document.getElementById("searchRadius").value = radius;
				}
			}
			function setCoordinatesInTextBox(center){
				//if the coordinates are determined internally, set the value shown in the textbox. null for blank
				if(center == null) {
					document.getElementById("searchCoordinates").value = "";
				}
				else {
					document.getElementById("searchCoordinates").value = locationToString(center);
				}
			}
			function setBucketSizeInTextBox(bucketSize){
				//if the graph bucket size is determined internally, set the value shown in the textbox. null for blank
				if(bucketSize == null) {
					document.getElementById("bucketSize").value = "";
				}
				else {
					document.getElementById("bucketSize").value = bucketSize;
				}
			}
			function setTotalStatsDisplay(numDays, numLogs){
				//used to set the first text display, which displays a summary of all the logs loaded at the outset
				document.getElementById("totalStats").innerHTML = "Loaded a total of " + numLogs + " logs from " + numDays + " days";
			}
			function setCurrentlyDisplayingStatisticsDisplay(numDays, numLogsSelected, numLogsDisplaying){ 
				//used to set the second text display, which displays a summary of all the logs currently being displayed
				if(numLogsDisplaying == numLogsSelected) {
					document.getElementById("currentlyDisplayingStats").innerHTML = "Displaying " + numLogsDisplaying + " logs from " + numDays + " days";
				}
				else {
					document.getElementById("currentlyDisplayingStats").innerHTML = "Displaying " + numLogsDisplaying + " of " + numLogsSelected + " logs from " + numDays + " days";
				}
			}
			function setMouseoverMessageDisplay(message){
				//used to set the third text display, which displays a variety of mouseover messages
				document.getElementById("mouseoverMessage").innerHTML = message;
			}
			function setLatLngDisp(loc){
				//sets the display at the top of the map with a location. null for blank
				if(loc == null) {
					document.getElementById("latLngDisp").innerHTML = "";
				}
				else {
					document.getElementById("latLngDisp").innerHTML = locationToString(loc);
				}
			}


			//comparisons
			function areDatesEqual(date1, date2){
				//compares the dates, but not the times, of two date objects
				return ((date1.getFullYear() == date2.getFullYear()) && (date1.getMonth() == date2.getMonth()) && (date1.getDate() == date2.getDate()));
			}
			function isStartBeforeEnd(potStart, potEnd){
				//determines whether the supplied start time is before the supplied end time
				return potStart.getTime() < potEnd.getTime();
			}
			function areOverlapping(loc1, loc2){
				//determines whether two displaying circles overlap (based on displayCircleRadius, obviously)
				var circleOptions = {
					center: loc1,
					radius:	displayCircleRadius * 2
				};

				var circ = new google.maps.Circle(circleOptions);
				return circ.getBounds().contains(loc2);
			}
			function doesCircleContain(circle, loc){
				//determines whether a location is actually inside a circle, not just its bounding box
				var dist = google.maps.geometry.spherical.computeDistanceBetween(circle.getCenter(), loc);
				return (dist < circle.getRadius());
			}
			function doesRegionContain(region, loc, pointOutside){
				//determines whether a location is actually inside a region, not just its bounding box. pointOutside can be any point outside the region (preferably close by)
				var path = region.getPath();
				var pathLength = path.getLength();

				//calculate the number of times the ray from the outside point to the inside point hits the edges of the region
				var totalIntersections = 0;
				for(var i = 0; i < pathLength; i++){
					if(doLinesIntersect(loc, pointOutside, path.getAt(i), path.getAt((i+1)%pathLength))){
						totalIntersections++;
					}
				}

				//iff the ray hits an odd number of times, the point is inside
				return ((totalIntersections % 2) == 1);
			}
			function doLinesIntersect(pointToTest, pointOutside, vx1, vx2) {
				//create cartesian points from the locations
				//POTENTIAL upgrade this conversion to make sure it actually works
				var ln1vx1 = {
					x: pointToTest.lng(),
					y: pointToTest.lat()
				};
				var ln1vx2 = {
					x: pointOutside.lng(),
					y: pointOutside.lat()
				};
				var ln2vx1 = {
					x: vx1.lng(),
					y: vx1.lat()
				};
				var ln2vx2 = {
					x: vx2.lng(),
					y: vx2.lat()
				};

		    var d1, d2;
		    var a1, a2, b1, b2, c1, c2;

		    //calculate the coeffecients of ax+by+c=0 for the first ray
		    a1 = ln1vx2.y - ln1vx1.y;
		    b1 = ln1vx1.x - ln1vx2.x;
		    c1 = (ln1vx2.x * ln1vx1.y) - (ln1vx1.x * ln1vx2.y);

		    d1 = (a1 * ln2vx1.x) + (b1 * ln2vx1.y) + c1;
		    d2 = (a1 * ln2vx2.x) + (b1 * ln2vx2.y) + c1;

		    //if the two points of the second ray are on the same side of the first line, they don't intersect
		    if (d1 * d2 > 0) return false;

		    //calculate the coeffecients of ax+by+c=0 for the second ray
		    a2 = ln2vx2.y - ln2vx1.y;
		    b2 = ln2vx1.x - ln2vx2.x;
		    c2 = (ln2vx2.x * ln2vx1.y) - (ln2vx1.x * ln2vx2.y);

		    d1 = (a2 * ln1vx1.x) + (b2 * ln1vx1.y) + c2;
		    d2 = (a2 * ln1vx2.x) + (b2 * ln1vx2.y) + c2;

		    //if the two points of the first ray are on the same side of the second line, they don't intersect
		    if (d1 * d2 > 0) return false;

		    //this is the colinear case, not really likely to be a big deal
		    if ((a1 * b2) - (a2 * b1) == 0) false;

		    return true;
			}


			//time conversions
			function makeCleanDate(year, month, day){
				//makes a date with just the date values, no time
				return new Date(year, month, day, 0, 0, 0, 0);
			}
			function stringToDate(str){
				//converts a string to a date. should be formatted "[day of week (irrelevant)] [month (e.g. January)] [day of month (e.g. 1st)], [year (e.g. 2013)]"
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
				//take a date and add the time. time string should be formatted "HH:mm:ss"
				var segments = timeStr.trim().split(":");
				existingDate.setHours(parseInt(segments[0]));
				existingDate.setMinutes(parseInt(segments[1]));
				existingDate.setSeconds(parseInt(segments[2]));
				return existingDate;
			}
			function removeTimeFromDate(existingDate){
				//take the time off of a date 
				return new makeCleanDate(existingDate.getFullYear(), existingDate.getMonth(), existingDate.getDate());
			}
			function getHoursFromTimeStr(timeStr){
				//extract the hour value from a time string, formatted "HH:mm:ss"
				return parseInt(timeStr.split(":")[0].trim());
			}
			function getMinutesFromTimeStr(timeStr){
				//extract the minute value from a time string, formatted "HH:mm:ss"
				return parseInt(timeStr.split(":")[1].trim());
			}
			function dateToShortStr(existingDate){
				//converts a date object to a formatted string "MM-dd-yyyy"
				var result = "";
				result += (existingDate.getMonth() + 1) + "-";
				result += existingDate.getDate() + "-";
				result += existingDate.getFullYear();
				return result;
			}

			//location conversions
			function makeBounds(customPolygon){
				//makes the smallest rectangular bounds around a region
				if(customPolygon.getPaths().length > 1) {
					alert("two path polygon!");
				}

				var path = customPolygon.getPath();
				var bounds = new google.maps.LatLngBounds(path.getAt(0), path.getAt(0));

				for(var i = 1; i < path.getLength(); i++){
					bounds.extend(path.getAt(i));
				}

				return bounds;
			}
			function locationToString(loc){
				//converts a location to a string, rounding to six figures after the decimal
				var lat = (Math.floor(loc.lat() * 1000000 + .5) / 1000000) + "";
				if(lat.indexOf(".") == -1) {
					lat += ".";
				}
				lat = (lat + "000000").slice(0, 7 + lat.indexOf("."));
				
				var lng = (Math.floor(loc.lng() * 1000000 + .5) / 1000000) + "";
				if(lng.indexOf(".") == -1) {
					lng += ".";
				}
				lng = (lng + "000000").slice(0, 7 + lng.indexOf("."));
				
				return lat + ", " + lng;
			}
			function stringToLocation(str){
				//converts a string to a location. string should be formatted "[lat], [lng]"
				var coords = str.trim().split(" ");
				var lat = parseFloat(coords[0]);
				var lng = parseFloat(coords[1]);
				return new google.maps.LatLng(lat, lng);
			}

			google.maps.event.addDomListener(window, "load", initializeMap);		
		</script>
	</head>
  <body onresize="resize()">
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
					<input type="date" id="startDate" class="controls" onchange="startDateChanged()" required="required"> 
					<input type="time" id="startTime" class="controls" onchange="validateAndSetSearchStartDate()" required="required">
					<input type="date" id="endDate" class="controls" onchange="endDateChanged()" required="required"> 
					<input type="time" id="endTime" class="controls" onchange="validateAndSetSearchEndDate()" required="required">
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
					<input type="text" id="displayRadius" class="controls" onchange="validateAndSetDisplayRadius()" placeholder="Display Radius (default 30 ft)">
					<input type="text" id="displayOpacity" class="controls" onchange="validateAndSetDisplayOpacity()" placeholder="Display Opacity (default .15)">
					<input type="text" id="bucketSize" class="controls" onchange="validateAndSetGraphBucketSize()" placeholder="Graph Bucket Size (default 1 day)">
					<input type="checkbox" id="connections" onchange="toggleDrawConnections()">Draw connections</input>
				</div>
				<div>
					<br><br><br>
					<p id="totalStats"><p>
					<p id="currentlyDisplayingStats"><p>
					<p id="mouseoverMessage"><p>
				</div>
				<div>
				  <button type="button" id="closeButton">Close Street View</button>
    			<p id="latLngDisp"><p>
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