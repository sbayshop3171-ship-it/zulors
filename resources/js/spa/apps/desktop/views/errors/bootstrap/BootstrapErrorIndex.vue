<template>
    <div class="fixed inset-0 bg-fill-qt grid grid-cols-2">
		<div class="w-full p-12">
			<div class="flex mb-4">
				<img v-bind:src="$embedder('assets.logos.url')" alt="Zulors" class="w-8">
			</div>
			<h1 class="text-[94px] font-bold text-red-800 leading-none mb-2">
				Bootstrap <br>
				<span>Error!</span>
			</h1>
			<p class="text-par-l text-lab-pr3 font-medium">
				Something went wrong while loading <br> the application.
			</p>
		</div>
		<div class="w-full p-12 bg-bg-pr">
			<div class="h-full flex flex-col">
				<div class="mb-12">
					<h4 class="text-title-1 font-bold mb-2 text-lab-pr2">
						Why does this happen?
					</h4>
					<p class="text-lab-sc">
						This error can occur for various reasons, including due to an incorrect installation and configuration of the application on the server side.
					</p>
				</div>
				<h4 class="text-par-m font-bold mb-2 text-lab-pr2">
					Common Reasons:
				</h4>
				<ul class="text-lab-pr2">
					<li>
						Invalidated session &#10060; 
					</li>
					<li>
						Invalidated CSRF token &#10060;
					</li>
					<li>
						Invalid cookie &#10060; 
					</li>
				</ul>
	
				<div class="border-t border-edge-pr my-4"></div>
	
				<h4 class="text-par-m font-bold mb-2 text-lab-pr2">
					How to fix:
				</h4>
				<ul class="text-lab-pr2">
					<li>
						Clear your browser cache and cookies 🧹
					</li>
					<li>
						Back to home page and start over ⭳
					</li>
				</ul>
	
				<a href="/" class="rounded-md smoothing border-2 border-dashed hover:border-lab-pr hover:text-lab-pr border-lab-sc text-lab-sc mt-auto text-par-l font-semibold block p-6 text-center cursor-pointer">
					Try Again
				</a>
			</div>
		</div>
	</div>
</template>
