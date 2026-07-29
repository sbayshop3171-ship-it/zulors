<template>
    <div class="fixed inset-0 bg-bg-sc overflow-y-auto">
        <RouterLink v-bind:to="{ name: 'home_index' }" class="fixed top-6 right-8 z-50">
            <button class="size-8 text-lab-sc hover:text-lab-pr">
                <SvgIcon name="x"></SvgIcon>
            </button>
        </RouterLink>

        <div class="w-content ml-12 md:ml-navbar py-top-offset text-lab-pr">
            <div class="mb-6">
                <div class="mb-4">
                    <h1 class="text-[72px] font-extrabold leading-none mb-2">Hello!</h1>
                    <h3 class="text-par-l font-bold">I'm Mansur Terla. Developer and Owner of Zulors script.</h3>
                </div>

                <div class="border-y border-lab-pr2 pb-4">
                    <div class="flex gap-2 mt-4 items-center leading-none">
                        <div class="size-12 shrink-0 rounded-full overflow-hidden flex justify-center items-center bg-fill-tr">
                            <svg class="size-full" viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect class="size-full" fill="#020202"/>
                                <path d="M93 198.394H124.902V312.606H93V198.394Z" fill="#D7D7D7"/>
                                <path d="M132.682 198.394H164.583V312.606H132.682V198.394Z" fill="#D7D7D7"/>
                                <path d="M172.366 198.394H204.267V312.606H172.366V198.394Z" fill="#D7D7D7"/>
                                <path d="M240.344 225.794H212.048V198H304.64V225.794H276.187V312.212H240.344V225.794Z" fill="#D7D7D7"/>
                                <path d="M303.843 198H339.473V283.42H380.282V311.102H303.843V198Z" fill="#D7D7D7"/>
                                <path d="M401.421 313C396.503 313 392.369 311.365 389.021 308.096C385.777 304.722 384.155 300.662 384.155 295.916C384.155 291.171 385.777 287.163 389.021 283.894C392.265 280.625 396.398 278.99 401.421 278.99C406.443 278.99 410.629 280.625 413.977 283.894C417.326 287.163 419 291.171 419 295.916C419 300.662 417.326 304.722 413.977 308.096C410.629 311.365 406.443 313 401.421 313Z" fill="#D7D7D7"/>
                            </svg>    
                        </div>
                        <div class="flex-1">
                            <h4 class="text-par-m font-bold text-lab-pr2 leading-tight truncate mb-1">
                                Mansur Terla
                            </h4>
                            <span class="block text-par-s text-lab-sc truncate">
                                Full-stack Web developer / UI/UX Designer.
                                <a href="https://www.terla.me" target="_blank" class="underline font-semibold">https://www.terla.me</a>
                            </span>
                        </div>
                    </div>
                    <div class="ml-14">
                        <div class="flex gap-2 mt-4">
                            <a href="https://t.me/mansurtl_contact" class="size-icon-small text-lab-sc hover:text-lab-pr2" target="_blank">
                                <SvgIcon name="telegram" type="social" classes="size-full"></SvgIcon>
                            </a>
                            <a href="https://www.instagram.com/mansur_terla" class="size-icon-small text-lab-sc hover:text-lab-pr2" target="_blank">
                                <SvgIcon name="instagram" type="social" classes="size-full"></SvgIcon>
                            </a>
                            <a href="https://x.com/mansur_terla" class="size-icon-small text-lab-sc hover:text-lab-pr2" target="_blank">
                                <SvgIcon name="x" type="social" classes="size-full"></SvgIcon>
                            </a>
                            
                            <a href="https://wa.me/89287442101" class="size-icon-small text-lab-sc hover:text-lab-pr2" target="_blank">
                                <SvgIcon name="whatsapp" type="social" classes="size-full"></SvgIcon>
                            </a>
                            <a href="https://vk.com/mansur_tl_uiux" class="size-icon-small text-lab-sc hover:text-lab-pr2" target="_blank">
                                <SvgIcon name="vk" type="social" classes="size-full"></SvgIcon>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
    
            <div class="mb-6">
                <h3 class="text-par-l font-bold mb-3">About Me</h3>
                <p class="text-par-m text-lab-pr2 mb-2">
                    I am a full stack web developer, skilled UI interface designer, and freelancer with over 8+ years of experience in web development. I am the creator of Zulors, a powerful social networking script, and its advanced successor, Zulors.
                </p>
                <p class="text-par-m text-lab-pr2 mb-2">
                    I am working independently to provide modern solutions for web design and development.
                </p>
                <p class="text-par-m text-lab-pr2 mb-2">
                    Before developing Zulors, I worked as a freelancer, collaborating with clients globally to build custom applications, design user interfaces, and solve complex development challenges.
                </p>
                <p class="text-par-m text-lab-pr2 mb-2">
                    My expertise spans both frontend and backend technologies, and I am fluent in Russian and English, allowing me to connect with a wide range of clients and developers.
                </p>
            </div>
            <div class="mb-6">
                <h3 class="text-par-l font-bold mb-3">What is Zulors?</h3>
                <p class="text-par-m text-lab-pr2 mb-2">
                    Zulors is the enhanced and improved version of Zulors, built from the ground up to address modern web development needs. 
                </p>
                <p class="text-par-m text-lab-pr2 mb-4">
                    While Zulors introduced a robust foundation for creating social networking platforms, Zulors takes it to the next level with significant advancements in performance, scalability, and features.
                </p>
                <p class="text-par-m text-lab-pr2 mb-4 font-bold">
                    Here’s what makes Zulors stand out:
                </p>

                <div class="text-par-s">
                    <div class="mb-4 border-y border-lab-pr2 py-4">
                        <b>1. Enhanced Architecture:</b>
                        <div>
                            Designed for better performance and easier customization.
                        </div>
                    </div>
    
                    <div class="mb-4 border-b border-lab-pr2 pb-4">
                        <b>2. Improved Real-Time Features:</b>
                        <div>
                            Designed for better performance and easier customization.
                        </div>
                    </div>
                    <div class="mb-4 border-b border-lab-pr2 pb-4">
                        <b>3. Advanced Tech Stack:</b>
                        <div>
                            Built with Laravel 11, VueJS 3, TailwindCSS, Redis, and MySQL to ensure modern, high-performing platforms.
                        </div>
                    </div>
                    <div class="mb-4 border-b border-lab-pr2 pb-4">
                        <b>4. Developer-Friendly Design:</b>
                        <div>
                            Simplified workflows and tools for faster and more efficient development.
                        </div>
                    </div>
                    <div class="mb-4 border-b border-lab-pr2 pb-4">
                        <b>5. Purpose:</b>
                        <div>
                            To empower developers and businesses to create cutting-edge social networks tailored to their specific needs.
                        </div>
                    </div>
                </div>

                <p class="text-par-m text-lab-pr2 mb-4 font-bold">
                    Zulors is offered under a lifetime license with free updates, ensuring your platform evolves with the latest technologies and features.
                </p>

                <div>
                    <a href="https://colibri.plus/" target="_blank" class="text-cap-l text-brand-900 underline font-medium">
                        Read more about Zulors
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, onMounted } from 'vue';

    import { useRouter } from 'vue-router';

    export default defineComponent({
        setup: function(props) {
            onMounted(() => {
                if(window.HIDE_AUTHOR_ATTRIBUTION) {
                    useRouter().push({
                        name: 'home_index'
                    });
                }
            });
        }
    });
</script>