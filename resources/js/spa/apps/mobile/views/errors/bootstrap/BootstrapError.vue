<template>
	<div class="py-12">
		<div class="p-8">
			<div class="mb-4 border-b border-edge-pr pb-4">
				<div class="flex mb-4">
					<img v-bind:src="$embedder('assets.logos.url')" alt="Zulors" class="w-8">
				</div>
				<h1 class="text-title-2 font-bold mb-2">
					Bootstrap Error
				</h1>
				<p class="text-par-l text-lab-sc">
					Something went wrong while loading the application.
				</p>
			</div>
			<div class="text-left">
				<h4 class="text-par-m font-bold mb-2 text-lab-pr2">
					Common Reasons:
				</h4>
				<ul class="list-disc list-inside mb-6">
					<li>
						Invalidated session
					</li>
					<li>
						Invalidated CSRF token
					</li>
					<li>
						Invalid cookie
					</li>
				</ul>

				<div class="border-t border-edge-pr my-4"></div>

				<h4 class="text-par-m font-bold mb-2 text-lab-pr2">
					How to fix:
				</h4>
				<ul class="list-disc list-inside mb-6">
					<li>
						Clear your browser cache and cookies
					</li>
					<li>
						Back to home page and start over
					</li>
				</ul>

				<a href="/" class="text-lab-pr font-semibold underline block">Home page</a>
			</div>
		</div>
	</div>
</template>
