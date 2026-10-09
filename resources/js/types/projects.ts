export type Project = {
    id: number;
    name: string;
    slug: string;
    methodology: 'kanban';
    status: 'active' | 'archived';
    createdBy: string;
    createdAt: string | null;
};

export type PaginatedProjects = {
    data: Project[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

export type BoardColumn = {
    id: number;
    name: string;
    category:
        | 'backlog'
        | 'todo'
        | 'in_progress'
        | 'review'
        | 'done'
        | 'cancelled';
    position: number;
    wipLimit: number | null;
};

export type Board = {
    id: number;
    name: string;
    slug: string;
    columns: BoardColumn[];
};
