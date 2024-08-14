import { ReactNode } from 'react'

import Header from '@/components/ui/Navigation/Header.tsx'
import { Route } from '@/components/ui/Navigation/Navigation.types.ts'
import Sidebar from '@/components/ui/Navigation/Sidebar/Sidebar.tsx'

interface Props {
    routes: Route[]
    children?: ReactNode
}

const AppLayout = ({ routes, children }: Props) => {
    return (
        <div className='flex min-h-screen w-full bg-muted/40'>
            <Sidebar routes={routes} />
            <div className='flex grow flex-col overflow-x-hidden sm:gap-4 sm:py-4'>
                <Header routes={routes} />
                <main className={'h-full  p-4 @container  sm:px-6 sm:py-0'}>
                    <div className={'flex h-full flex-col gap-2 @md:gap-4'}>
                        {children}
                    </div>
                </main>
            </div>
        </div>
    )
}

export default AppLayout
