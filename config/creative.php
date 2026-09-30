<?php

return [
    'environments' => ['studio', 'nature', 'luxury', 'urban', 'home', 'abstract'],
    'video_durations' => [5, 8, 10],

    'surfaces' => [
        'default' => [
            'key' => 'default',
            'label' => 'استودیویی خنثی',
            'prompt' => '',
            'icon' => '⚪',
        ],
        'marble' => [
            'key' => 'marble',
            'label' => 'سنگ مرمر لوکس',
            'prompt' => 'displayed on a luxury white veined Carrara marble pedestal with soft specular highlights',
            'icon' => '🏛️',
        ],
        'wood' => [
            'key' => 'wood',
            'label' => 'پایه چوب طبیعی',
            'prompt' => 'staged on a solid rustic natural wood slab with rich warm grain texture',
            'icon' => '🪵',
        ],
        'concrete' => [
            'key' => 'concrete',
            'label' => 'سکوی بتنی مینیمال',
            'prompt' => 'positioned on an architectural polished minimal concrete podium',
            'icon' => '🧱',
        ],
        'water' => [
            'key' => 'water',
            'label' => 'سطح آب با امواج',
            'prompt' => 'resting on a clear crystal water surface with gentle ripples and caustic light reflections',
            'icon' => '💧',
        ],
        'obsidian' => [
            'key' => 'obsidian',
            'label' => 'آبسیدین صیقلی',
            'prompt' => 'elevated on a glossy black obsidian mirror surface with sharp glossy ground reflections',
            'icon' => '💎',
        ],
        'sand' => [
            'key' => 'sand',
            'label' => 'ماسه کویر',
            'prompt' => 'nestled on fine warm golden desert sand with subtle wind-blown dunes',
            'icon' => '🏜️',
        ],
    ],

    'props' => [
        'none' => [
            'key' => 'none',
            'label' => 'بدون اکسسوری اضافه',
            'prompt' => '',
            'icon' => '✨',
        ],
        'botanical' => [
            'key' => 'botanical',
            'label' => 'ارگانیک و گیاهی',
            'prompt' => 'accented with lush monstera leaves, olive branches, and delicate pink orchid petals',
            'icon' => '🌿',
        ],
        'splash' => [
            'key' => 'splash',
            'label' => 'پاشش قطرات معلق',
            'prompt' => 'surrounded by dynamic crystal water splashes, microscopic floating droplets, and morning dew',
            'icon' => '💦',
        ],
        'smoke' => [
            'key' => 'smoke',
            'label' => 'دود و مه ملایم',
            'prompt' => 'enveloped in gentle atmospheric ethereal fog and soft cinematic dry-ice smoke wisps',
            'icon' => '🌫️',
        ],
        'crystals' => [
            'key' => 'crystals',
            'label' => 'کریستال‌ها و هندسی',
            'prompt' => 'flanked by floating geometric glass prisms and translucent crystal shards scattering spectrum colors',
            'icon' => '🔮',
        ],
    ],

    'camera_angles' => [
        'eye_level' => [
            'key' => 'eye_level',
            'label' => 'زاویه روبرو',
            'prompt' => 'Camera perspective: straight eye-level studio commercial shot with natural perspective',
            'icon' => '👁️',
        ],
        'flat_lay' => [
            'key' => 'flat_lay',
            'label' => 'عکاسی از بالا (Flat-Lay)',
            'prompt' => 'Camera perspective: crisp 90-degree overhead top-down flat-lay composition',
            'icon' => '📐',
        ],
        'hero_shot' => [
            'key' => 'hero_shot',
            'label' => 'زاویه پایین حماسی (Hero)',
            'prompt' => 'Camera perspective: powerful low-angle heroic viewpoint creating grand scale and presence',
            'icon' => '👑',
        ],
        'macro' => [
            'key' => 'macro',
            'label' => 'نمای کلوزآپ/ماکرو',
            'prompt' => 'Camera perspective: intimate ultra-close macro detail shot highlighting premium texture and craftsmanship',
            'icon' => '🔍',
        ],
    ],

    'lighting_setups' => [
        'softbox' => [
            'key' => 'softbox',
            'label' => 'سافت‌باکس استودیویی',
            'prompt' => 'Lighting: balanced high-end dual commercial softbox studio lighting with diffused even illumination',
            'icon' => '💡',
        ],
        'sunlight' => [
            'key' => 'sunlight',
            'label' => 'نور طبیعی پنجره',
            'prompt' => 'Lighting: warm afternoon window sunlight streaming through with soft cinematic architectural shadows',
            'icon' => '☀️',
        ],
        'rim' => [
            'key' => 'rim',
            'label' => 'نور لبه‌ای دراماتیک',
            'prompt' => 'Lighting: dramatic high-contrast edge rim lighting sculpting the product contours against a moody backdrop',
            'icon' => '⚡',
        ],
        'neon' => [
            'key' => 'neon',
            'label' => 'نور نئون سایبرنتیک',
            'prompt' => 'Lighting: futuristic duotone cyber neon backlight with subtle magenta and cyan ambient glow',
            'icon' => '🟣',
        ],
    ],
];
