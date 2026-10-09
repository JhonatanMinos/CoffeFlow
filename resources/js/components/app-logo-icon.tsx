import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <title>CoffeeFlow</title>
            <path d="M4 5h13v9a6 6 0 0 1-6 6h-1a6 6 0 0 1-6-6V5Zm13 2h1.5a3.5 3.5 0 0 1 0 7H17V7ZM8 1c0 1.5 1 1.5 1 3H7c0-1.5-1-1.5-1-3h2Zm5 0c0 1.5 1 1.5 1 3h-2c0-1.5-1-1.5-1-3h2Z" />
        </svg>
    );
}
