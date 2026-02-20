declare module 'laravel-permission-to-vuejs' {

    const defaultExport: {
        install: (app: any, options?: any) => void
    }

    export const is: (value: string) => boolean
    export const can: (value: string) => boolean
    export const reloadRolesAndPermissions: (route?: string) => Promise<void>

    export default defaultExport
}