<!--
    PERSONNALY - SVG Filters pour techniques de personnalisation
    Ce fichier doit être inclus une seule fois dans le <body> de chaque page
    qui utilise les techniques (product.php, cart.php, lightbox, admin/order.php)
-->
<svg width="0" height="0" style="position:absolute;visibility:hidden;">
    <defs>
        <!-- ============================================
             BRODERIE VARIANTE A - Premium / Sobre
             Relief subtil, texture légère, très lisible
             ============================================ -->
        <filter id="broderie-a" x="-20%" y="-20%" width="140%" height="140%">
            <!-- Légère texture de fil (grain subtil) -->
            <feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="3" seed="15" result="noise"/>
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="0.8" xChannelSelector="R" yChannelSelector="G" result="displaced"/>

            <!-- Ombre portée réaliste (fils qui montent du tissu) -->
            <feDropShadow dx="1.5" dy="2" stdDeviation="0.8" flood-color="rgba(0,0,0,0.35)" result="shadow"/>

            <!-- Léger highlight sur le dessus (brillance fil) -->
            <feSpecularLighting in="displaced" surfaceScale="1.5" specularConstant="0.4" specularExponent="25" lighting-color="#ffffff" result="specular">
                <fePointLight x="-5000" y="-5000" z="8000"/>
            </feSpecularLighting>
            <feComposite in="specular" in2="SourceGraphic" operator="in" result="specularMasked"/>

            <!-- Assemblage final -->
            <feMerge>
                <feMergeNode in="shadow"/>
                <feMergeNode in="displaced"/>
                <feMergeNode in="specularMasked"/>
            </feMerge>
        </filter>

        <!-- ============================================
             BRODERIE VARIANTE B - Texture marquée
             Relief prononcé, grain visible, effet cousu
             ============================================ -->
        <filter id="broderie-b" x="-25%" y="-25%" width="150%" height="150%">
            <!-- Texture de fil plus marquée -->
            <feTurbulence type="fractalNoise" baseFrequency="1.2" numOctaves="4" seed="42" result="noise"/>
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="1.5" xChannelSelector="R" yChannelSelector="G" result="displaced"/>

            <!-- Double ombre pour plus de profondeur -->
            <feDropShadow dx="0.5" dy="0.8" stdDeviation="0.3" flood-color="rgba(0,0,0,0.2)" result="innerShadow"/>
            <feDropShadow in="innerShadow" dx="2" dy="2.5" stdDeviation="1" flood-color="rgba(0,0,0,0.4)" result="outerShadow"/>

            <!-- Highlight plus prononcé -->
            <feSpecularLighting in="displaced" surfaceScale="2.5" specularConstant="0.6" specularExponent="20" lighting-color="#ffffff" result="specular">
                <fePointLight x="-5000" y="-5000" z="6000"/>
            </feSpecularLighting>
            <feComposite in="specular" in2="SourceGraphic" operator="in" result="specularMasked"/>

            <!-- Légère ligne directionnelle (simule direction des fils) -->
            <feConvolveMatrix in="displaced" order="3" kernelMatrix="0 -0.5 0 1 2 1 0 -0.5 0" result="embossed"/>
            <feBlend in="embossed" in2="displaced" mode="overlay" result="textured"/>

            <!-- Assemblage final -->
            <feMerge>
                <feMergeNode in="outerShadow"/>
                <feMergeNode in="textured"/>
                <feMergeNode in="specularMasked"/>
            </feMerge>
        </filter>

        <!-- ============================================
             FLEX - Filtre léger (brillance vinyle)
             ============================================ -->
        <filter id="flex-shine" x="-5%" y="-5%" width="110%" height="110%">
            <feSpecularLighting surfaceScale="2" specularConstant="0.8" specularExponent="35" lighting-color="#ffffff" result="specular">
                <fePointLight x="-3000" y="-3000" z="4000"/>
            </feSpecularLighting>
            <feComposite in="specular" in2="SourceGraphic" operator="in" result="specularMasked"/>
            <feMerge>
                <feMergeNode in="SourceGraphic"/>
                <feMergeNode in="specularMasked"/>
            </feMerge>
        </filter>

        <!-- ============================================
             FLOCK - Filtre velours (flou doux)
             ============================================ -->
        <filter id="flock-velvet" x="-10%" y="-10%" width="120%" height="120%">
            <feTurbulence type="fractalNoise" baseFrequency="2" numOctaves="3" result="noise"/>
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="0.5" result="textured"/>
            <feGaussianBlur in="textured" stdDeviation="0.3" result="blurred"/>
            <feMerge>
                <feMergeNode in="blurred"/>
            </feMerge>
        </filter>
    </defs>
</svg>
