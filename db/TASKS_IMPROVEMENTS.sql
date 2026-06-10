-- Mejoras para el sistema de Tareas
-- Agregar tablas para recordatorios, dependencias y vista Kanban

-- Agregar campos a tabla tasks existente
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS parent_task_id INTEGER REFERENCES tasks(id) ON DELETE SET NULL;
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS kanban_column VARCHAR(20) DEFAULT 'todo'; -- todo, in_progress, review, done
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS reminder_sent BOOLEAN DEFAULT false;
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS reminder_time TIMESTAMP;
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS position_order INTEGER DEFAULT 0;

-- Índices para tareas
CREATE INDEX IF NOT EXISTS idx_tasks_user_status ON tasks(user_id, status);
CREATE INDEX IF NOT EXISTS idx_tasks_due_date ON tasks(due_date);
CREATE INDEX IF NOT EXISTS idx_tasks_kanban ON tasks(user_id, kanban_column, position_order);
CREATE INDEX IF NOT EXISTS idx_tasks_parent ON tasks(parent_task_id);

-- Tabla de dependencias de tareas
CREATE TABLE IF NOT EXISTS task_dependencies (
    id SERIAL PRIMARY KEY,
    task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    depends_on_task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    dependency_type VARCHAR(20) DEFAULT 'finish_to_start', -- finish_to_start, start_to_start, finish_to_finish
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (task_id, depends_on_task_id)
);

CREATE INDEX IF NOT EXISTS idx_task_dependencies_task ON task_dependencies(task_id);
CREATE INDEX IF NOT EXISTS idx_task_dependencies_depends ON task_dependencies(depends_on_task_id);

-- Tabla de recordatorios de tareas
CREATE TABLE IF NOT EXISTS task_reminders (
    id SERIAL PRIMARY KEY,
    task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    reminder_time TIMESTAMP NOT NULL,
    reminder_type VARCHAR(20) DEFAULT 'email', -- email, push, sms
    is_sent BOOLEAN DEFAULT false,
    sent_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_task_reminders_task ON task_reminders(task_id);
CREATE INDEX IF NOT EXISTS idx_task_reminders_time ON task_reminders(reminder_time);
CREATE INDEX IF NOT EXISTS idx_task_reminders_sent ON task_reminders(is_sent);

-- Tabla de historial de cambios de tareas
CREATE TABLE IF NOT EXISTS task_history (
    id SERIAL PRIMARY KEY,
    task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    changed_by INTEGER NOT NULL REFERENCES users(id),
    change_type VARCHAR(50) NOT NULL, -- status_change, priority_change, assignment_change, etc.
    old_value JSONB,
    new_value JSONB,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_task_history_task ON task_history(task_id);
CREATE INDEX IF NOT EXISTS idx_task_history_user ON task_history(changed_by);
CREATE INDEX IF NOT EXISTS idx_task_history_date ON task_history(changed_at DESC);

-- Función para verificar si una tarea puede completarse (dependencias cumplidas)
CREATE OR REPLACE FUNCTION can_complete_task(task_id INTEGER)
RETURNS BOOLEAN AS $$
DECLARE
    dependency_count INTEGER;
BEGIN
    SELECT COUNT(*) INTO dependency_count
    FROM task_dependencies td
    JOIN tasks t ON t.id = td.depends_on_task_id
    WHERE td.task_id = task_id
        AND t.status != 'completed';

    RETURN dependency_count = 0;
END;
$$ LANGUAGE plpgsql;

-- Función para actualizar posición de tareas en columna Kanban
CREATE OR REPLACE FUNCTION reorder_kanban_tasks(user_id INTEGER, column_name VARCHAR, task_id INTEGER, new_position INTEGER)
RETURNS VOID AS $$
BEGIN
    -- Actualizar posición de la tarea movida
    UPDATE tasks
    SET position_order = new_position
    WHERE id = task_id;

    -- Reordenar otras tareas en la misma columna
    UPDATE tasks
    SET position_order = position_order + 1
    WHERE user_id = user_id
        AND kanban_column = column_name
        AND id != task_id
        AND position_order >= new_position;
END;
$$ LANGUAGE plpgsql;

-- Comentarios
COMMENT ON COLUMN tasks.kanban_column IS 'Columna en vista Kanban (todo, in_progress, review, done)';
COMMENT ON COLUMN tasks.parent_task_id IS 'Tarea padre para sub-tareas';
COMMENT ON COLUMN tasks.reminder_sent IS 'Indica si ya se envió el recordatorio';
COMMENT ON TABLE task_dependencies IS 'Dependencias entre tareas';
COMMENT ON TABLE task_reminders IS 'Recordatorios programados para tareas';
COMMENT ON TABLE task_history IS 'Historial de cambios en tareas';
COMMENT ON FUNCTION can_complete_task(INTEGER) IS 'Verifica si todas las dependencias de una tarea están cumplidas';
COMMENT ON FUNCTION reorder_kanban_tasks(INTEGER, VARCHAR, INTEGER, INTEGER) IS 'Reordena tareas en columna Kanban';
