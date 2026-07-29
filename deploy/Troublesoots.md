# Livewire.JS not found issue with Nginx:
	Make sure that you have published
	livewire assets.

	Use the following command to do it:
		- php artisan livewire:publish --assets
		- Or with docker
		- docker-compose exec app php artisan livewire:publish --assets
		- if you are using docker.