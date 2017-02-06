/*$('#main-content-form').on('submit', function() {
	console.log('1');
	logInfo();

	return false;
});*/

$('#main-content-form').submit(function(event) {
	console.log('1');

	event.preventDefault();
	logInfo();

	return false;
});

function logInfo() {
	console.log('2');
	var httpRequest;

	var url = '../autolog/php/WorkFlow.php';

	var numeroSerie = document.getElementById('numeroSerie').value;
	var handleUsuario = document.getElementById('handleUsuario').value;
	var handleLinha = document.getElementById('handleLinha').value;

	if(window.XMLHttpRequest) {
		httpRequest = new XMLHttpRequest();
	}

	if(!httpRequest) {
		alert('Problemas com JavaScript!');
	}

	httpRequest.open('POST', url, true);
	httpRequest.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	httpRequest.onreadystatechange = function() {
		if(httpRequest.readyState === 4) {
			console.log(httpRequest.responseText);
			var response = JSON.parse(httpRequest.responseText);

			document.getElementById('main-content-form').style.display = 'none';
			document.getElementById('response').style.display = 'flex';

			if(httpRequest.status === 200) {
				if(response.status == 'success') {
					document.getElementById('response-error').style.display = 'none';

					document.getElementById('response-success-message').innerHTML = response.message;
					document.getElementById('response-success-details').innerHTML = response.details;
					document.getElementById('response-success').style.display = 'flex';
				} else if(response.status == 'fail') {
					document.getElementById('response-success').style.display = 'none';

					document.getElementById('response-error-message').innerHTML = response.message;
					document.getElementById('response-error-details').innerHTML = response.details;
					document.getElementById('response-error').style.display = 'flex';
				}
			} else {
				document.getElementById('response-success').style.display = 'none';

				document.getElementById('response-error-message').innerHTML = response.message;
				document.getElementById('response-error-details').innerHTML = response.details;
				document.getElementById('response-error').style.display = 'flex';
			}
		}
	};

	httpRequest.send(
		'serial=' + encodeURIComponent(numeroSerie) + '&' +
		'opid=' + encodeURIComponent(handleUsuario) + '&' +
		'line=' + encodeURIComponent(handleLinha)
	);
}
